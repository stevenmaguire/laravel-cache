<?php namespace Stevenmaguire\Laravel\Contracts;

interface Cacheable
{
    /**
     * Flush the cache for the concrete service
     *
     * @return void
     */
    public function flushCache();
}
