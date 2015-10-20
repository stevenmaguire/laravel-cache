# Laravel Cache Services

[![Latest Version](https://img.shields.io/github/release/stevenmaguire/laravel-cache.svg?style=flat-square)](https://github.com/stevenmaguire/laravel-cache/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/stevenmaguire/laravel-cache/master.svg?style=flat-square)](https://travis-ci.org/stevenmaguire/laravel-cache)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/stevenmaguire/laravel-cache.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/laravel-cache/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/stevenmaguire/laravel-cache.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/laravel-cache)
[![Total Downloads](https://img.shields.io/packagist/dt/stevenmaguire/laravel-cache.svg?style=flat-square)](https://packagist.org/packages/league/laravel-cache)

Seamlessly adding caching to Laravel Eloquent service objects.

## Install

Via Composer

``` bash
$ composer require stevenmaguire/laravel-cache
```

## Usage

### Include the base service

Extend your service class with the EloquentCache class.

```php
class UserRegistrar extends Stevenmaguire\Laravel\Services\EloquentCache
{
    //
}
```

Implement the interface and use the trait.

```php
class UserRegistrar implements Stevenmaguire\Laravel\Contracts\Cacheable
{
    use \Stevenmaguire\Laravel\Services\EloquentCacheTrait;
}
```

### Implement abstract methods

```php
    /**
     * Get cache key from concrete service
     *
     * @return string
     */
    abstract protected function getCacheKey();

    /**
     * Get model from concrete service
     *
     * @return Illuminate\Database\Eloquent\Model
     */
    abstract protected function getModel();
```


`getCacheKey` is intended to provide cache key grouping at the repository level. It will attempt to use this as a prefix when creating cache keys.

`getModel` is intended to return the model associated with the repository in question, assuming you have one repo per entity.

### Construct queries

Build queries using Eloquent and request cache object.

```php
use App\User;
use Stevenmaguire\Laravel\Services\EloquentCache;

class UserRegistrar extends EloquentCache
{
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    protected function getCacheKey()
    {
        return 'users';
    }

    protected function getModel()
    {
        return $this->user;
    }

    public function getAllUsers()
    {
        $query = $this->user->query();

        return $this->cache('all', $query);
    }

    public function getUserById($id)
    {
        $query = $this->user->where('id', $id);

        return $this->cache('id('.$id.')', $query, 'first');
    }

    public function getRecent($skip = 0, $take = 100)
    {
        $query = $this->user->orderBy('created_at', 'desc')
            ->take($take)
            ->skip($skip);

        return $this->cache('recent('.$skip.','.$take.')', $query);
    }
}
```
The `cache` method takes three parameters:

- The unique key associated with the method's intentions
- The query `Builder` object for the Eloquent query
- The optional verb, `get`, `first`, `list`, `paginate` etc; `get` by default

If the method associated with the optional verb takes parameters, like `paginate`, the parameters can be expressed as a comma separated list following the verb and a colon. If a parameter expects an array of literal values, these may be expressed as a pipe delimited sting.

```php
/**
 * Paginate users with all pagination parameters
 */
public function getAllUsers()
{
    $query = $this->user->query();

    return $this->cache('all', $query, 'paginate:15,id|email|name,sheet,2');
    // $query->paginate(15, ['id', 'email', 'name'], 'sheet', 2);
}
```

The cache service will automatically index all of the unique keys used by your application. These keys will be used when the `flushCache` method is called on each service implementing the base cache service.

### Configure caching

For each of the services you implement using the `EloquentCache` you can configure the following:

#### Duration of cache minutes

```php
use Stevenmaguire\Laravel\Services\EloquentCache;

class UserRegistrar extends EloquentCache
{
    protected $cacheForMinutes = 15;

    //
}
```

#### Disable caching

```php
use Stevenmaguire\Laravel\Services\EloquentCache;

class UserRegistrar extends EloquentCache
{
    protected $enableCaching = false;

    //
}
```

#### Disable logging

```php
use Stevenmaguire\Laravel\Services\EloquentCache;

class UserRegistrar extends EloquentCache
{
    protected $enableLogging = false;

    //
}
```

#### Set a custom cache index for the keys

```php
use Stevenmaguire\Laravel\Services\EloquentCache;

class UserRegistrar extends EloquentCache
{
    protected $cacheIndexKey = 'my-service-keys-index';

    //
}
```

### Flushing cache

Each service object that implements caching via `Stevenmaguire\Laravel\Services\EloquentCache` can flush its own cache, independently of other consuming services.

Your application can flush the cache for all keys within the service object `serviceKey` group.

```php
$userRegistrar->flushCache();
```

Your application can only flush the cache for keys within the service object `serviceKey` group that match a particular regular expression pattern.

```php
// Flush cache for all cached users with a single digit user id
$userRegistrar->flushCache('id\([0-9]{1}\)');
```

### Bind to IoC Container

```php
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->when('App\Handlers\Events\UserHandler')
          ->needs('Stevenmaguire\Laravel\Contracts\Cacheable')
          ->give('App\Services\UserRegistrar');
    }
}
```

In this particular example, `UserHandler` is responsible for flushing the user service cache when a specific event occurs. The `UserHandler` takes a dependacy on the `flushCache` method within the `UserRegistrar` service.


## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email stevenmaguire@gmail.com instead of using the issue tracker.

## Credits

- [Steven Maguire](https://github.com/stevenmaguire)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
