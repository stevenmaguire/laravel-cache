<?php namespace Stevenmaguire\Laravel\Test;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use Stevenmaguire\Laravel\Services\EloquentCache;

class EloquentCacheTraitTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->builder = m::mock(Builder::class);
        $this->collection = m::mock(Collection::class);
        $this->service = m::mock(ConcreteTest::class)
            ->shouldDeferMissing()
            ->shouldAllowMockingProtectedMethods();
    }

    private function makeArray($count, $assoc = false)
    {
        $array = [];

        for ($i = 0; $i < $count; $i++) {
            if ($assoc) {
                $array[uniqid(1)] = uniqid(2);
            } else {
                $array[$i] = uniqid(2);
            }
        }

        return $array;
    }

    public function testItWillNotLogWhenLoggingDisabled()
    {
        $this->service->setEnableLogging(false);
        $count = rand(2,10);
        $cacheKey = uniqid();
        $serviceKeys = $this->makeArray($count, true);
        $this->service->setCacheIndexKey($cacheKey);
        $this->service->shouldReceive('getServiceKeys')->once()->andReturn($serviceKeys);
        CacheFacade::shouldReceive('forget')->times($count);

        $this->service->flushCache();
    }

    public function testItWillLogWhenLoggingEnabled()
    {
        $this->service->setEnableLogging(true);
        $count = rand(2,10);
        $cacheKey = uniqid();
        $serviceKeys = $this->makeArray($count, true);
        $this->service->setCacheIndexKey($cacheKey);
        $this->service->shouldReceive('getServiceKeys')->once()->andReturn($serviceKeys);
        CacheFacade::shouldReceive('forget')->times($count);
        Log::shouldReceive('info')->times($count);

        $this->service->flushCache();
    }

    public function testItCanFlushCacheForService()
    {
        $count = rand(2,10);
        $serviceKeys = $this->makeArray($count, true);
        $cacheKey = uniqid();
        $this->service->setCacheIndexKey($cacheKey);
        $this->service->shouldReceive('getServiceKeys')->once()->andReturn($serviceKeys);
        Log::shouldReceive('info')->times($count);
        CacheFacade::shouldReceive('forget')->times($count);

        $this->service->flushCache();
    }

    public function testItCanSetServiceKeys()
    {
        $count = rand(2,10);
        $keys = $this->makeArray($count, true);
        $existingKeys = $this->makeArray($count, true);
        $cacheKey = uniqid();
        $serviceKey = uniqid();
        $mergedKeys = array_merge($existingKeys, [$serviceKey => $keys]);
        $this->service->setCacheIndexKey($cacheKey);
        $this->service->shouldReceive('getKeys')->once()->andReturn($existingKeys);
        $this->service->shouldReceive('getCacheKey')->once()->andReturn($serviceKey);
        CacheFacade::shouldReceive('forever')->with($cacheKey, $mergedKeys)->times($count);

        $this->service->setServiceKeys($keys);
    }

    public function testItCanIndexKeys()
    {
        $count = rand(2,10);
        $keys = $this->makeArray($count);
        $key = uniqid();
        $mergedKeys = array_merge($keys, [$key]);
        $this->service->shouldReceive('getServiceKeys')->once()->andReturn($keys);
        $this->service->shouldReceive('setServiceKeys')->with($mergedKeys)->once();

        $this->service->indexKey($key);
    }

    public function testItCanGetKeys()
    {
        $cacheKey = uniqid();
        $this->service->setCacheIndexKey($cacheKey);
        CacheFacade::shouldReceive('get')->with($cacheKey, [])->once();

        $this->service->getKeys();
    }

    public function testItCanGetServiceKeysWhenNotPreviouslySet()
    {
        $count = rand(2,10);
        $cacheKey = uniqid();
        $existingKeys = $this->makeArray($count, true);
        $this->service->setCacheKey($cacheKey);
        $this->service->shouldReceive('getKeys')->once()->andReturn($existingKeys);

        $serviceKeys = $this->service->getServiceKeys();

        $this->assertEmpty($serviceKeys);
        $this->assertTrue(is_array($serviceKeys));
    }

    public function testItCanGetServiceKeysWhenPreviouslySetAsNonArray()
    {
        $count = rand(2,10);
        $serviceKey = uniqid();
        $cacheKey = uniqid();
        $existingKeys = $this->makeArray($count, true);
        $existingKeys[$cacheKey] = $serviceKey;
        $expectedKeys = [$serviceKey];
        $this->service->setCacheKey($cacheKey);
        $this->service->shouldReceive('getKeys')->once()->andReturn($existingKeys);

        $serviceKeys = $this->service->getServiceKeys();

        $this->assertEquals($expectedKeys, $serviceKeys);
    }

    public function testItCanGetServiceKeysWhenPreviouslySetAsArray()
    {
        $count = rand(2,10);
        $cacheKey = uniqid();
        $serviceKey = $this->makeArray($count);
        $existingKeys = $this->makeArray($count, true);
        $existingKeys[$cacheKey] = $serviceKey;
        $expectedKeys = $serviceKey;
        $this->service->setCacheKey($cacheKey);
        $this->service->shouldReceive('getKeys')->once()->andReturn($existingKeys);

        $serviceKeys = $this->service->getServiceKeys();

        $this->assertEquals($expectedKeys, $serviceKeys);
    }

    public function testItCanGetCacheSelectorWithoutId()
    {
        $cacheKey = uniqid();
        $this->service->setCacheKey($cacheKey);

        $cacheSelector = $this->service->getCacheSelector();

        $this->assertEquals($cacheKey, $cacheSelector);
    }

    public function testItCanGetCacheSelectorWithId()
    {
        $id = uniqid(1);
        $cacheKey = uniqid(2);
        $expectedSelector = $cacheKey.'.'.$id;
        $this->service->setCacheKey($cacheKey);

        $cacheSelector = $this->service->getCacheSelector($id);

        $this->assertEquals($expectedSelector, $cacheSelector);
    }

    public function testItCanGetAttributeFromCollectionWhenValidAttributeAndValueGiven()
    {
        $count = rand(2,10);
        $attribute = uniqid();
        $value = uniqid();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $item = new \stdClass;
            $item->$attribute = $value;
            array_push($items, $item);
        }
        $collection = new Collection($items);

        $result = $this->service->getByAttributeFromCollection($collection, $attribute, $value)->all();

        $this->assertEquals($items, $result);
    }

    public function testItCanNotGetAttributeFromCollectionWhenValidAttributeAndInvalidValueGiven()
    {
        $count = rand(2,10);
        $attribute = uniqid();
        $value = uniqid();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $item = new \stdClass;
            $item->$attribute = $value;
            array_push($items, $item);
        }
        $collection = new Collection($items);

        $result = $this->service->getByAttributeFromCollection($collection, $attribute)->all();

        $this->assertEmpty($result);
    }

    public function testItCanNotGetAttributeFromCollectionWhenInvalidAttributeGiven()
    {
        $count = rand(2,10);
        $attribute = uniqid();
        $value = uniqid();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $item = new \stdClass;
            $item->$attribute = $value;
            array_push($items, $item);
        }
        $collection = new Collection($items);

        $result = $this->service->getByAttributeFromCollection($collection, uniqid())->all();

        $this->assertEmpty($result);
    }

    public function testItWillCacheForeverWhenCachingIsEnabledAndCacheMinutesIsZero()
    {
        $key = uniqid();
        $query = $this->builder;
        $verb = 'get';
        $this->service->setCacheForMinutes(0)->setEnableCaching(true);
        $this->service->shouldReceive('getCacheSelector')->with($key)->once()->andReturn($key);
        $this->service->shouldReceive('indexKey')->with($key)->once();
        CacheFacade::shouldReceive('rememberForever')->with($key, m::any())->once();

        $this->service->cache($key, $query);
    }

    public function testItWillCacheForeverWhenCachingIsEnabledAndCacheMinutesIsLessThanZero()
    {
        $key = uniqid();
        $query = $this->builder;
        $verb = 'get';
        $minutes = -1 * abs(rand(1,60));
        $this->service->setCacheForMinutes($minutes)->setEnableCaching(true);
        $this->service->shouldReceive('getCacheSelector')->with($key)->once()->andReturn($key);
        $this->service->shouldReceive('indexKey')->with($key)->once();
        CacheFacade::shouldReceive('rememberForever')->with($key, m::any())->once();

        $this->service->cache($key, $query);
    }

    public function testItWillCacheForGivenMinutesWhenCachingIsEnabledAndCacheMinutesIsGreaterThanZero()
    {
        $key = uniqid();
        $query = $this->builder;
        $verb = 'get';
        $minutes = rand(1,60);
        $this->service->setCacheForMinutes($minutes)->setEnableCaching(true);
        $this->service->shouldReceive('getCacheSelector')->with($key)->once()->andReturn($key);
        $this->service->shouldReceive('indexKey')->with($key)->once();
        CacheFacade::shouldReceive('remember')->with($key, $minutes, m::any())->once();

        $this->service->cache($key, $query);
    }

    public function testItWillNotCacheWhenCachingIsNotEnabled()
    {
        $key = uniqid();
        $query = $this->builder;
        $verb = uniqid();
        $this->service->setEnableCaching(false);
        $this->service->shouldReceive('getCacheSelector')->with($key)->once()->andReturn($key);
        $this->service->shouldReceive('indexKey')->with($key)->once();
        $this->service->shouldReceive('log')->once();
        $query->shouldReceive($verb)->once();

        $this->service->cache($key, $query, $verb);
    }
}
