<?php namespace Stevenmaguire\Laravel\Test;

use Illuminate\Database\Eloquent\Model;
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
        return $testCacheKey;
    }

    public function setCacheKey($key)
    {
        $this->testCacheKey = $key;

        return $this;
    }

    /**
     * Get model from concrete service
     *
     * @return Illuminate\Database\Eloquent\Model
     */
    public function getModel()
    {

    }

    public function setModel(Model $model)
    {
        $this->testModel = $model;

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
}
