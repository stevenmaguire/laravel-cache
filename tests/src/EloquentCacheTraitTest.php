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
        $this->service->setEnableLogging(false);
    }

    private function setUpKeys($start, $newKey = null)
    {
        $this->withExistingKeys($start);
        $updatedKeys = $start;

        if ($newKey) {
            array_push($updatedKeys, $newKey);
            $this->withUpdatedKeys($updatedKeys);
        }

        return $this;
    }

    private function withExistingKeys(array $keys)
    {
        $serviceKey = 'service-key';
        $this->service->setCacheKey($serviceKey);

        CacheFacade::shouldReceive('get')
            ->with($this->service->getCacheIndexKey(), [])
            ->once()
            ->andReturn([$this->service->getCacheKey() => $keys]);

        return $this;
    }

    private function withUpdatedKeys(array $keys)
    {
        CacheFacade::shouldReceive('forever')
            ->with($this->service->getCacheIndexKey(), [$this->service->getCacheKey() => $keys])
            ->once();

        return $this;
    }

    private function willFetchData($data, $verbKey = 'get')
    {
        $verbParts = $this->service->getVerbParts($verbKey);
        $verb = $verbParts[0];
        $params = $verbParts[1];
        $this->service->setEnableCaching(false);
        if (empty($params)) {
           $this->builder->shouldReceive($verb)->andReturn($data);
        } else {
            $this->builder->shouldReceive($verb)->withArgs($params)->andReturn($data);
        }

        return $this;
    }

    private function willCacheData($key, $minutes = 0)
    {
        $this->service->setEnableCaching(true);
        $this->service->setCacheForMinutes($minutes);
        $actualKey = $this->service->getCacheKey().'.'.$key;
        $callback = m::type('callable');

        if ($this->service->isCacheForever()) {
            CacheFacade::shouldReceive('rememberForever')->with($actualKey, $callback);
        } else {
            CacheFacade::shouldReceive('remember')->with($actualKey, $this->service->getCacheMinutes(), $callback);
        }

        return $this;
    }

    private function willLog()
    {
        $this->service->setEnableLogging(true);
        Log::shouldReceive('info')->with(m::type('string'));

        return $this;
    }

    private function willForget($key)
    {
        $actualKey = $this->service->getCacheKey().'.'.$key;
        CacheFacade::shouldReceive('forget')->with($actualKey);

        return $this;
    }

    private function getRandomCollection($attribute, $seedValue = null)
    {
        $count = rand(2,10);
        $value = $seedValue ?: uniqid();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $item = new \stdClass;
            $item->$attribute = $value;
            array_push($items, $item);
        }

        return new Collection($items);
    }

    public function testItWillRefreshCacheWhenKeyNotPresentAndCacheEnabledAndWithoutCacheDuration()
    {
        $newKey = uniqid();
        $this->setUpKeys([], $newKey)
            ->willCacheData($newKey);

        $this->service->cache($newKey, $this->builder);
    }

    public function testItWillRefreshCacheWhenKeyNotPresentAndCacheEnabledAndWithCacheDuration()
    {
        $newKey = uniqid();
        $this->setUpKeys([], $newKey)
            ->willCacheData($newKey, 10);

        $this->service->cache($newKey, $this->builder);
    }

    public function testItWillNotRefreshCacheWhenCacheNotEnabled()
    {
        $newKey = uniqid();
        $verb = 'paginate:15,test|test';
        $this->setUpKeys([], $newKey)
            ->willFetchData([], $verb)
            ->willLog();

        $this->service->cache($newKey, $this->builder, $verb);
    }

    public function testItCanGetAttributeFromCollectionWhenValidAttributeAndValueGiven()
    {
        $attribute = uniqid();
        $value = uniqid();
        $collection = $this->getRandomCollection($attribute, $value);

        $result = $this->service->getByAttributeFromCollection($collection, $attribute, $value)->all();

        $this->assertEquals($collection->all(), $result);
    }

    public function testItCanNotGetAttributeFromCollectionWhenValidAttributeAndInvalidValueGiven()
    {
        $attribute = uniqid();
        $collection = $this->getRandomCollection($attribute);

        $result = $this->service->getByAttributeFromCollection($collection, $attribute)->all();

        $this->assertEmpty($result);
    }

    public function testItCanNotGetAttributeFromCollectionWhenInvalidAttributeGiven()
    {
        $attribute = uniqid();
        $collection = $this->getRandomCollection($attribute);

        $result = $this->service->getByAttributeFromCollection($collection, uniqid())->all();

        $this->assertEmpty($result);
    }

    public function testItWillGetServiceKeys()
    {
        $keys = [uniqid(), uniqid(), uniqid()];
        $this->setUpKeys($keys);

        $serviceKeys = $this->service->getServiceKeys();

        $this->assertEquals($keys, $serviceKeys);
    }

    public function testItCanFlushCacheForServiceWithPattern()
    {
        $pattern = '^test\.[0-9]$';
        $keys = ['test.1','test.one','test.10','test.ten'];
        $this->setUpKeys($keys)
            ->willForget('test.1')
            ->willLog();

        $this->service->flushCache($pattern);
    }
}
