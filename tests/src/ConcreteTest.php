<?php namespace Stevenmaguire\Laravel\Test;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Stevenmaguire\Laravel\Services\EloquentCache;

class ConcreteTest extends EloquentCache
{
    protected $testCacheKey;
    protected $testModel;

    /**
     * Get cache key from concrete service
     *
     * @return string
     */
    public function getCacheKey()
    {
        return $this->testCacheKey;
    }

    public function setCacheKey($key)
    {
        $this->testCacheKey = $key;

        return $this;
    }

    public function getModel()
    {

    }

    public function getCacheIndexKey()
    {
        return $this->cacheIndexKey;
    }

    public function setCacheIndexKey($key)
    {
        $this->cacheIndexKey = $key;

        return $this;
    }

    public function setCacheForMinutes($minutes)
    {
        $this->cacheForMinutes = $minutes;

        return $this;
    }

    public function setEnableCaching($enable = true)
    {
        $this->enableCaching = $enable;

        return $this;
    }

    public function setEnableLogging($enable = true)
    {
        $this->enableLogging = $enable;

        return $this;
    }

    public function setServiceKeys($keys = [])
    {
        return parent::setServiceKeys($keys);
    }

    public function getServiceKeys()
    {
        return parent::getServiceKeys();
    }

    public function indexKey($key)
    {
        return parent::indexKey($key);
    }

    public function getKeys()
    {
        return parent::getKeys();
    }

    public function getCacheSelector($id = null)
    {
        return parent::getCacheSelector($id);
    }

    public function getByAttributeFromCollection(Collection $collection, $attribute, $value = null)
    {
        return parent::getByAttributeFromCollection($collection, $attribute, $value);
    }

    public function cache($key, Builder $query, $verb = 'get')
    {
        return parent::cache($key, $query, $verb);
    }
}
