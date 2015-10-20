<?php namespace Stevenmaguire\Laravel\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Log;

trait EloquentCacheTrait
{
    /**
     * Cache duration in minutes; 0 is forever
     *
     * @var int
     */
    protected $cacheForMinutes = 0;

    /**
     * Enable caching
     *
     * @var boolean
     */
    protected $enableCaching = true;

    /**
     * Enable logging
     *
     * @var boolean
     */
    protected $enableLogging = true;

    /**
     * Key used to store index of all service keys
     *
     * @var string
     */
    protected $cacheIndexKey = 'cache-index';

    /**
     * Retrieve from cache if not empty, otherwise store results
     * of query in cache
     *
     * @param  string   $key
     * @param  Builder  $query
     * @param  string   $verb Optional Builder verb to execute query
     *
     * @return Collection|Model|array|null
     */
    protected function cache($key, Builder $query, $verb = 'get')
    {
        $key = $this->getCacheSelector($key);

        $this->indexKey($key);

        $fetchData = function () use ($key, $query, $verb) {
            $this->log('refreshing cache for '.get_class($this).' ('.$key.')');

            return $this->callQueryVerb($query, $verb);
        };

        if ($this->enableCaching) {
            if ($this->cacheForMinutes > 0) {
                return CacheFacade::remember($key, $this->cacheForMinutes, $fetchData);
            }

            return CacheFacade::rememberForever($key, $fetchData);
        }

        return $fetchData();
    }

    /**
     * Attempts to deconstruct verb into method name and parameters to call on
     * query builder object.
     *
     * @param  Builder  $query
     * @param  string   $verbKey
     *
     * @return Collection|Model|array|null
     */
    protected function callQueryVerb(Builder $query, $verbKey)
    {
        $verbParts = explode(':', $verbKey);
        $verb = array_shift($verbParts);
        $params = [];

        if (!empty($verbParts)) {
            $params = array_map(function ($param) {
                $subParams = explode('|', $param);

                return count($subParams) > 1 ? $subParams : $subParams[0];
            }, explode(',', array_shift($verbParts)));
        }

        return call_user_func_array([$query, $verb], $params);
    }

    /**
     * Get items from collection whose properties match a given attribute and value
     *
     * @param  Collection  $collection
     * @param  string      $attribute
     * @param  mixed       $value
     *
     * @return Collection
     */
    protected function getByAttributeFromCollection(Collection $collection, $attribute, $value = null)
    {
        return $collection->filter(function ($item) use ($attribute, $value) {
            if (isset($item->$attribute) && $value) {
                return $item->$attribute == $value;
            }

            return false;
        });
    }

    /**
     * Get cache key from concrete service
     *
     * @return string
     */
    abstract protected function getCacheKey();

    /**
     * Create and get cache selector
     *
     * @param  string  $id Optional id to suffix base key
     *
     * @return string
     */
    protected function getCacheSelector($id = null)
    {
        return $this->getCacheKey().($id ? '.'.$id : '');
    }

    /**
     * Get keys from key inventory
     *
     * @return array
     */
    protected function getKeys()
    {
        return CacheFacade::get($this->cacheIndexKey, []);
    }

    /**
     * Get model from concrete service
     *
     * @return Illuminate\Database\Eloquent\Model
     */
    abstract protected function getModel();

    /**
     * Get keys for concrete service
     *
     * @return array
     */
    protected function getServiceKeys()
    {
        $keys = $this->getKeys();
        $serviceKey = $this->getCacheKey();

        if (!isset($keys[$serviceKey])) {
            $keys[$serviceKey] = [];
        } elseif (!is_array($keys[$serviceKey])) {
            $keys[$serviceKey] = [$keys[$serviceKey]];
        }

        return $keys[$serviceKey];
    }

    /**
     * Index a given key in the service key inventory
     *
     * @param  string $key
     *
     * @return void
     */
    protected function indexKey($key)
    {
        $keys = $this->getServiceKeys();

        array_push($keys, $key);

        $keys = array_unique($keys);

        $this->setServiceKeys($keys);
    }

    /**
     * Log the message, if enabled
     *
     * @param  string $message
     *
     * @return void
     */
    protected function log($message)
    {
        if ($this->enableLogging) {
            Log::info($message);
        }
    }

    /**
     * Set keys for concrete service
     *
     * @param array $keys
     */
    protected function setServiceKeys($keys = [])
    {
        $allkeys = $this->getKeys();
        $serviceKey = $this->getCacheKey();

        $allkeys[$serviceKey] = $keys;

        CacheFacade::forever($this->cacheIndexKey, $allkeys);
    }

    /**
     * Flush the cache for the concrete service
     *
     * @return void
     */
    public function flushCache()
    {
        $keys = $this->getServiceKeys();

        array_map(function ($key) {
            $this->log('flushing cache for '.get_class($this).' ('.$key.')');

            CacheFacade::forget($key);
        }, $keys);
    }
}
