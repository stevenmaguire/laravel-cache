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
        $actualKey = $this->indexKey($key);

        $fetchData = function () use ($actualKey, $query, $verb) {
            $this->log('refreshing cache for '.get_class($this).' ('.$actualKey.')');

            return $this->callQueryVerb($query, $verb);
        };

        if ($this->enableCaching) {
            if ($this->cacheForMinutes > 0) {
                return CacheFacade::remember($actualKey, $this->cacheForMinutes, $fetchData);
            }

            return CacheFacade::rememberForever($actualKey, $fetchData);
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
        $verb = static::getVerbParts($verbKey);

        return call_user_func_array([$query, $verb[0]], $verb[1]);
    }

    /**
     * Iterates over given array to remove values that don't match a given
     * regular expression pattern.
     *
     * @param  array   $values
     * @param  string  $pattern
     *
     * @return array
     */
    public static function filterArrayValuesWithPattern(array $values, $pattern)
    {
        return array_values(
            array_filter(
                array_map(function ($key) use ($pattern) {
                    if ((bool) preg_match('/'.$pattern.'/', $key)) {
                        return $key;
                    }
                }, $values)
            )
        );
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
    protected function getCacheKey()
    {
        return get_class($this);
    }

    /**
     * Creates fully qualified key.
     *
     * @param  string  $suffix
     *
     * @return string
     */
    protected function getFullKey($suffix)
    {
        return $this->getCacheKey().'.'.$suffix;
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
     * Get keys for concrete service
     *
     * @param  string $pattern
     *
     * @return array
     */
    protected function getServiceKeys($pattern = null)
    {
        $keys = $this->getKeys();
        $serviceKey = $this->getCacheKey();
        $serviceKeys = isset($keys[$serviceKey]) ? $keys[$serviceKey] : [];

        if (!is_null($pattern)) {
            $serviceKeys = $this->filterArrayValuesWithPattern(
                $serviceKeys,
                $pattern
            );
        }

        return $serviceKeys;
    }

    /**
     * Attempts to deconstruct verb into method name and parameters.
     *
     * @param  string   $verbKey
     *
     * @return array
     */
    public static function getVerbParts($verbKey)
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

        return [$verb, $params];
    }

    /**
     * Index a given key in the service key inventory
     *
     * @param  string $key
     *
     * @return string
     */
    protected function indexKey($key)
    {
        $keys = $this->getServiceKeys();

        array_push($keys, $key);

        $keys = array_unique($keys);

        $this->setServiceKeys($keys);

        return $this->getFullKey($key);
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
     * @param  string $pattern
     *
     * @return void
     */
    public function flushCache($pattern = null)
    {
        $keys = $this->getServiceKeys($pattern);

        array_map(function ($key) {
            $actualKey = $this->getFullKey($key);
            $this->log('flushing cache for '.get_class($this).' ('.$actualKey.')');

            CacheFacade::forget($actualKey);
        }, $keys);
    }
}
