<?php

namespace Illuminate\Tests\Console\Scheduling;

use JMac\Testing\TestDouble;
use Illuminate\Cache\ArrayStore;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\CacheSchedulingMutex;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class CacheSchedulingMutexTest extends TestCase
{
    /**
     * @var \Illuminate\Console\Scheduling\CacheSchedulingMutex
     */
    protected $cacheMutex;

    /**
     * @var \Illuminate\Console\Scheduling\Event
     */
    protected $event;

    /**
     * @var \Illuminate\Support\Carbon
     */
    protected $time;

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
        $this->cacheMutex = new CacheSchedulingMutex($this->cacheFactory);
        $this->event = new Event(new CacheEventMutex($this->cacheFactory), 'command');
        $this->time = Carbon::now();
    }

    public function testMutexReceivesCorrectCreate()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('add')->with($this->event->mutexName().$this->time->format('Hi'), true, 3600)->returns(true);

        $this->assertTrue($this->cacheMutex->create($this->event, $this->time));
    }

    public function testCanUseCustomConnection()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheFactory->allows('store')->with('test')->returns($this->cacheRepository);
        $this->cacheRepository->expects('add')->with($this->event->mutexName().$this->time->format('Hi'), true, 3600)->returns(true);
        $this->cacheMutex->useStore('test');

        $this->assertTrue($this->cacheMutex->create($this->event, $this->time));
    }

    public function testPreventsMultipleRuns()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('add')->with($this->event->mutexName().$this->time->format('Hi'), true, 3600)->returns(false);

        $this->assertFalse($this->cacheMutex->create($this->event, $this->time));
    }

    public function testChecksForNonRunSchedule()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->expects('has')->with($this->event->mutexName().$this->time->format('Hi'))->returns(false);

        $this->assertFalse($this->cacheMutex->exists($this->event, $this->time));
    }

    public function testChecksForAlreadyRunSchedule()
    {
        $this->cacheRepository->allows('getStore')->returns(new \stdClass);
        $this->cacheRepository->allows('has')->with($this->event->mutexName().$this->time->format('Hi'))->returns(true);

        $this->assertTrue($this->cacheMutex->exists($this->event, $this->time));
    }

    public function testMutexReceivesCorrectCreateWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        $this->assertTrue($this->cacheMutex->create($this->event, $this->time));
    }

    public function testPreventsMultipleRunsWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        // first create the lock, so we can test that the next call fails.
        $this->cacheMutex->create($this->event, $this->time);

        $this->assertFalse($this->cacheMutex->create($this->event, $this->time));
    }

    public function testChecksForNonRunScheduleWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        $this->assertFalse($this->cacheMutex->exists($this->event, $this->time));
    }

    public function testChecksForAlreadyRunScheduleWithLockProvider()
    {
        $this->cacheRepository->allows('getStore')->returns(new ArrayStore);

        $this->cacheMutex->create($this->event, $this->time);

        $this->assertTrue($this->cacheMutex->exists($this->event, $this->time));
    }
}
