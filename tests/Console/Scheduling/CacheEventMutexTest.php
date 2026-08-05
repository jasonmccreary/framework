<?php

namespace Illuminate\Tests\Console\Scheduling;

use JMac\Testing\TestDouble;
use Illuminate\Cache\ArrayStore;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use PHPUnit\Framework\TestCase;

class CacheEventMutexTest extends TestCase
{
    /**
     * @var \Illuminate\Console\Scheduling\CacheEventMutex
     */
    protected $cacheMutex;

    /**
     * @var \Illuminate\Console\Scheduling\Event
     */
    protected $event;

    /**
     * @var \Illuminate\Contracts\Cache\Factory
     */
    protected $cacheFactory;

    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cacheRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheFactory = TestDouble::for(Factory::class);
        $this->cacheRepository = TestDouble::for(Repository::class);
        $this->cacheFactory->allows('store')->returns($this->cacheRepository);
        $this->cacheMutex = new CacheEventMutex($this->cacheFactory);
        $this->event = new Event($this->cacheMutex, 'command');
    }

    public function testPreventOverlap()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('add');

        $this->cacheMutex->create($this->event);
    }

    public function testCustomConnection()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheFactory->allows('store')->with('test')->returns($this->cacheRepository);
        $this->cacheRepository->expects('add');
        $this->cacheMutex->useStore('test');

        $this->cacheMutex->create($this->event);
    }

    public function testPreventOverlapFails()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('add')->returns(false);

        $this->assertFalse($this->cacheMutex->create($this->event));
    }

    public function testOverlapsForNonRunningTask()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('has')->returns(false);

        $this->assertFalse($this->cacheMutex->exists($this->event));
    }

    public function testOverlapsForRunningTask()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('has')->returns(true);

        $this->assertTrue($this->cacheMutex->exists($this->event));
    }

    public function testResetOverlap()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('forget');

        $this->cacheMutex->forget($this->event);
    }

    public function testPreventOverlapWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        $this->assertTrue($this->cacheMutex->create($this->event));
    }

    public function testPreventOverlapFailsWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        // first create the lock, so we can test that the next call fails.
        $this->cacheMutex->create($this->event);

        $this->assertFalse($this->cacheMutex->create($this->event));
    }

    public function testOverlapsForNonRunningTaskWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        $this->assertFalse($this->cacheMutex->exists($this->event));
    }

    public function testOverlapsForRunningTaskWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        $this->cacheMutex->create($this->event);

        $this->assertTrue($this->cacheMutex->exists($this->event));
    }

    public function testResetOverlapWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        $this->cacheMutex->create($this->event);

        $this->cacheMutex->forget($this->event);

        $this->assertFalse($this->cacheMutex->exists($this->event));
    }
}
