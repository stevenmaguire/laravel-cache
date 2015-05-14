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
        $this->service = m::mock(new ConcreteTest)
            ->shouldDeferMissing()
            ->shouldAllowMockingProtectedMethods();;
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
        $serviceKeys = $this->makeArray($count);
        $this->service->shouldReceive('getServiceKeys')->once()->andReturn($serviceKeys);
        CacheFacade::shouldReceive('forget')->times($count);

        $this->service->flushCache();
    }

    public function testItCanFlushCacheForService()
    {
        $count = rand(2,10);
        $serviceKeys = $this->makeArray($count);
        $this->service->shouldReceive('getServiceKeys')->once()->andReturn($serviceKeys);
        Log::shouldReceive('info')->times($count);
        CacheFacade::shouldReceive('forget')->times($count);

        $this->service->flushCache();
    }
}
