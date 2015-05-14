<?php namespace Stevenmaguire\Laravel\Services;

use Stevenmaguire\Laravel\Contracts\Cacheable;

abstract class EloquentCache implements Cacheable
{
    use EloquentCacheTrait;
}
