<?php

namespace Illuminate\Tests\Console;

use Illuminate\Console\CacheCommandMutex;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;

class CacheCommandMutexTest extends TestCase
{
    use VerifiesDoubles;

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
        $this->cacheFactory->expects('store')->with('test')->returns($this->cacheRepository);
        $this->cacheRepository->expects('getStore')->times(2)->returns($lock);

        $this->acquireLockExpectations($lock, true);
        $this->mutex->useStore('test');

        $this->mutex->create($this->command);
    }

    /**
     * @return void
     */
    private function mockUsingCacheStore(): void
    {
        $this->cacheFactory->expects('store')->returns($this->cacheRepository);
        $this->cacheRepository->expects('getStore')->returns(null);
    }

    private function mockUsingLockProvider(): LockProvider
    {
        $lock = TestDouble::for(LockProvider::class);
        $this->cacheFactory->expects('store')->returns($this->cacheRepository);
        $this->cacheRepository->expects('getStore')->times(2)->returns($lock);

        return $lock;
    }

    private function acquireLockExpectations(LockProvider $lock, bool $acquiresSuccessfully): void
    {
        $lock->expects('lock')
            ->with(Argument::type('string'), Argument::type('int'))
            ->returns($lock);

        $lock->expects('get')
            ->returns($acquiresSuccessfully);
    }

    public function testCommandMutexNameWithoutIsolatedMutexNameMethod()
    {
        $this->mockUsingCacheStore();

        $this->cacheRepository->allows('getStore')->with('test')->returns($this->cacheRepository);

        $this->cacheRepository->expects('add')
            ->with(
                Argument::satisfies(function ($key) {
                    $this->assertSame('framework'.DIRECTORY_SEPARATOR.'command-command-name', $key);

                    return true;
                }),
                Argument::any(),
                Argument::any(),
            )
            ->returns(true);

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

        $this->cacheRepository->expects('add')
            ->with(
                Argument::satisfies(function ($key) {
                    $this->assertSame('framework'.DIRECTORY_SEPARATOR.'command-command-name-isolated', $key);

                    return true;
                }),
                Argument::any(),
                Argument::any(),
            )
            ->returns(true);

        $this->mutex->create($command);
    }
}
