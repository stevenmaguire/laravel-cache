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
     * @return Collection|Model|[]|null
     */
    protected function cache($key, Builder $query, $verb = 'get')
    {
        $key = $this->getCacheSelector($key);

        $this->indexKey($key);

        return CacheFacade::rememberForever($key, function () use ($key, $query, $verb) {
            $this->log('refreshing cache for '.get_class($this).' ('.$key.')');

            return $query->$verb();
        });
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
        return $collection->filter(function($item) use ($attribute, $value) {
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
    private function getCacheSelector($id = null)
    {
        return $this->getCacheKey().($id ? '.'.$id : '');
    }

    /**
     * Get keys from key inventory
     *
     * @return array
     */
    private function getKeys()
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
    private function getServiceKeys()
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
    private function indexKey($key)
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
    private function log($message)
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
    private function setServiceKeys($keys = [])
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
