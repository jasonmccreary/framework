<?php

namespace Illuminate\Tests\Cache;

use JMac\Testing\TestDouble;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\FailoverStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\CanFlushLocks;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class CacheFailoverStoreTest extends TestCase
{
    public function testImplementsCanFlushLocks()
    {
        $store = $this->makeFailoverStore([]);

        $this->assertInstanceOf(CanFlushLocks::class, $store);
    }

    public function testFlushLocksCallsFlushLocksOnAllBackingStores()
    {
        $storeA = new ArrayStore;
        $storeB = new ArrayStore;

        $storeA->lock('lock-a', 60)->get();
        $storeB->lock('lock-b', 60)->get();

        $cache = TestDouble::for(CacheManager::class);
        $cache->allows('store')->with('store-a')->returns(new Repository($storeA));
        $cache->allows('store')->with('store-b')->returns(new Repository($storeB));

        $failover = new FailoverStore($cache, TestDouble::for(Dispatcher::class), ['store-a', 'store-b']);

        $result = $failover->flushLocks();

        $this->assertTrue($result);
        $this->assertEmpty($storeA->locks);
        $this->assertEmpty($storeB->locks);
    }

    public function testFlushLocksReturnsTrueWhenNoStoreSupportsIt()
    {
        $store = $this->makeFailoverStore([]);

        $this->assertTrue($store->flushLocks());
    }

    protected function makeFailoverStore(array $stores): FailoverStore
    {
        return new FailoverStore(
            TestDouble::for(CacheManager::class),
            TestDouble::for(Dispatcher::class),
            $stores
        );
    }
}
