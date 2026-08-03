<?php

namespace Illuminate\Tests\Console;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use Illuminate\Console\CacheCommandMutex;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CacheCommandMutexTest extends TestCase
{
    /**
     * @var \Illuminate\Console\CacheCommandMutex
     */
    protected $mutex;

    /**
     * @var \Illuminate\Console\Command
     */
    protected $command;

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
        $this->cacheFactory = TestDouble::for(Factory::class);
        $this->cacheRepository = TestDouble::for(Repository::class);
        $this->mutex = new CacheCommandMutex($this->cacheFactory);
        $this->command = new class extends Command
        {
            protected $name = 'command-name';
        };
    }

    public function testCanCreateMutex()
    {
        $this->mockUsingCacheStore();
        $this->cacheRepository->expects('add')->returns(true);
        $actual = $this->mutex->create($this->command);

        $this->assertTrue($actual);
    }

    public function testCannotCreateMutexIfAlreadyExist()
    {
        $this->mockUsingCacheStore();
        $this->cacheRepository->expects('add')->returns(false);
        $actual = $this->mutex->create($this->command);

        $this->assertFalse($actual);
    }

    public function testCanCreateMutexWithCustomConnection()
    {
        $this->mockUsingCacheStore();
        $this->cacheRepository->allows('getStore')->with('test')->returns($this->cacheRepository);
        $this->cacheRepository->expects('add')->returns(false);
        $this->mutex->useStore('test');

        $this->mutex->create($this->command);
    }

    public function testCanCreateMutexWithLockProvider()
    {
        $lock = $this->mockUsingLockProvider();
        $this->acquireLockExpectations($lock, true);

        $actual = $this->mutex->create($this->command);

        $this->assertTrue($actual);
    }

    public function testCanCreateMutexWithCustomLockProviderConnection()
    {
        $this->mockUsingCacheStore();
        $this->cacheRepository->allows('getStore')->with('test')->returns($this->cacheRepository);
        $this->cacheRepository->expects('add')->returns(false);
        $this->mutex->useStore('test');

        $this->mutex->create($this->command);
    }

    public function testCannotCreateMutexIfAlreadyExistWithLockProvider()
    {
        $lock = $this->mockUsingLockProvider();
        $this->acquireLockExpectations($lock, false);
        $actual = $this->mutex->create($this->command);

        $this->assertFalse($actual);
    }

    public function testCanCreateMutexWithCustomConnectionWithLockProvider()
    {
        $lock = TestDouble::for(LockProvider::class);
        $this->cacheFactory->expects('store')->once()->with('test')->andReturn($this->cacheRepository);
        $this->cacheRepository->expects('getStore')->twice()->andReturn($lock);

        $this->acquireLockExpectations($lock, true);
        $this->mutex->useStore('test');

        $this->mutex->create($this->command);
    }

    /**
     * @return void
     */
    private function mockUsingCacheStore(): void
    {
        $this->cacheFactory->expects('store')->once()->andReturn($this->cacheRepository);
        $this->cacheRepository->expects('getStore')->andReturn(null);
    }

    private function mockUsingLockProvider(): m\MockInterface
    {
        $lock = TestDouble::for(LockProvider::class);
        $this->cacheFactory->expects('store')->once()->andReturn($this->cacheRepository);
        $this->cacheRepository->expects('getStore')->twice()->andReturn($lock);

        return $lock;
    }

    private function acquireLockExpectations(MockInterface $lock, bool $acquiresSuccessfully): void
    {
        $lock->expects('lock')
            ->once()
            ->with(Argument::type('string'), Argument::type('int'))
            ->andReturns($lock);

        $lock->expects('get')
            ->once()
            ->andReturns($acquiresSuccessfully);
    }

    public function testCommandMutexNameWithoutIsolatedMutexNameMethod()
    {
        $this->mockUsingCacheStore();

        $this->cacheRepository->allows('getStore')->with('test')->returns($this->cacheRepository);

        $this->cacheRepository->shouldReceive('add')
            ->once()
            ->withArgs(function ($key) {
                $this->assertSame('framework'.DIRECTORY_SEPARATOR.'command-command-name', $key);

                return true;
            })
            ->andReturn(true);

        $this->mutex->create($this->command);
    }

    public function testCommandMutexNameWithIsolatedMutexNameMethod()
    {
        $command = new class extends Command
        {
            protected $name = 'command-name';

            public function isolatableId()
            {
                return 'isolated';
            }
        };

        $this->mockUsingCacheStore();

        $this->cacheRepository->allows('getStore')->with('test')->returns($this->cacheRepository);

        $this->cacheRepository->shouldReceive('add')
            ->once()
            ->withArgs(function ($key) {
                $this->assertSame('framework'.DIRECTORY_SEPARATOR.'command-command-name-isolated', $key);

                return true;
            })
            ->andReturn(true);

        $this->mutex->create($command);
    }
}
