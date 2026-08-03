<?php

namespace Illuminate\Tests\Bus;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BusDispatcherTest extends TestCase
{
    public function testCommandsThatShouldQueueIsQueued()
    {
        $container = new Container;
        $container->instance('queue.routes', $queueRoutes = TestDouble::for(\stdClass::class));
        $queueRoutes->allows('getQueue')->returns(null);
        $queueRoutes->allows('getConnection')->returns(null);
        Container::setInstance($container);
        $dispatcher = new Dispatcher($container, function () {
            $mock = TestDouble::for(Queue::class);
            $mock->expects('push');

            return $mock;
        });

        $dispatcher->dispatch(TestDouble::for(ShouldQueue::class));

        Container::setInstance(null);
    }

    public function testCommandsThatShouldQueueIsQueuedUsingCustomHandler()
    {
        $container = new Container;
        $container->instance('queue.routes', $queueRoutes = TestDouble::for(\stdClass::class));
        $queueRoutes->allows('getQueue')->returns(null);
        $queueRoutes->allows('getConnection')->returns(null);
        Container::setInstance($container);
        $dispatcher = new Dispatcher($container, function () {
            $mock = TestDouble::for(Queue::class);
            $mock->expects('push');

            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherTestCustomQueueCommand);

        Container::setInstance(null);
    }

    public function testCommandsThatShouldQueueIsQueuedUsingCustomQueueAndDelay()
    {
        $container = new Container;
        $container->instance('queue.routes', $queueRoutes = TestDouble::for(\stdClass::class));
        $queueRoutes->allows('getQueue')->returns(null);
        $queueRoutes->allows('getConnection')->returns(null);
        Container::setInstance($container);
        $dispatcher = new Dispatcher($container, function () {
            $mock = TestDouble::for(Queue::class);
            $mock->expects('later')->with(10, Argument::type(BusDispatcherTestSpecificQueueAndDelayCommand::class), '', 'foo');

            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherTestSpecificQueueAndDelayCommand);

        Container::setInstance(null);
    }

    public function testCommandsAreDispatchedWithQueueRoute()
    {
        Container::setInstance($container = new Container);
        $container->instance('queue.routes', $queueRoutes = TestDouble::for(\stdClass::class));
        $queueRoutes->allows('getQueue')->returns('high-priority');
        $queueRoutes->allows('getConnection')->returns(null);

        $mock = TestDouble::for(Queue::class);
        $mock->expects('push')->with(BusDispatcherQueueable::class, '', 'high-priority');

        $dispatcher = new Dispatcher($container, function () use ($mock) {
            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherQueueable);

        Container::setInstance(null);
    }

    public function testDispatchNowShouldNeverQueue()
    {
        $container = new Container;
        $mock = TestDouble::for(Queue::class);
        $mock->expects('push')->never();
        $dispatcher = new Dispatcher($container, function () use ($mock) {
            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherBasicCommand);
    }

    public function testDispatcherCanDispatchStandAloneHandler()
    {
        $container = new Container;
        $mock = TestDouble::for(Queue::class);
        $dispatcher = new Dispatcher($container, function () use ($mock) {
            return $mock;
        });

        $dispatcher->map([StandAloneCommand::class => StandAloneHandler::class]);

        $response = $dispatcher->dispatch(new StandAloneCommand);

        $this->assertInstanceOf(StandAloneCommand::class, $response);
    }

    public function testOnConnectionOnJobWhenDispatching()
    {
        Container::setInstance($container = new Container);
        $container->singleton('config', function () {
            return new Config([
                'queue' => [
                    'default' => 'null',
                    'connections' => [
                        'null' => ['driver' => 'null'],
                    ],
                ],
            ]);
        });
        $container->instance('queue.routes', $queueRoutes = TestDouble::for(\stdClass::class));
        $queueRoutes->allows('getQueue')->returns(null);
        $queueRoutes->allows('getConnection')->returns(null);
        Container::setInstance($container);

        $dispatcher = new Dispatcher($container, function () {
            $mock = TestDouble::for(Queue::class);
            $mock->expects('push');

            return $mock;
        });

        $job = (new ShouldNotBeDispatched)->onConnection('null');

        $dispatcher->dispatch($job);

        Container::setInstance(null);
    }

    public function testDispatchBulk()
    {
        $container = new Container;
        $container->instance('queue.routes', $queueRoutes = TestDouble::for(\stdClass::class));
        $queueRoutes->allows('getQueue')->returns(null);
        $queueRoutes->allows('getConnection')->returns(null);
        Container::setInstance($container);

        $mock = TestDouble::for(Queue::class);
        $mock->expects('bulk')->with(Argument::satisfies(fn ($jobs) => count($jobs) === 2), '', null);
        $mock->expects('bulk')->with(Argument::satisfies(fn ($jobs) => count($jobs) === 1), '', 'high');

        $dispatcher = new Dispatcher($container, fn () => $mock);

        $dispatcher->bulk([
            new BusDispatcherQueueable,
            new BusDispatcherQueueable,
            new BusDispatcherTestSpecificQueueCommand,
        ]);

        Container::setInstance(null);
    }
}

class BusInjectionStub
{
    //
}

class BusDispatcherBasicCommand
{
    public $name;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function handle(BusInjectionStub $stub)
    {
        //
    }
}

class BusDispatcherTestCustomQueueCommand implements ShouldQueue
{
    public function queue($queue, $command)
    {
        $queue->push($command);
    }
}

class BusDispatcherTestSpecificQueueAndDelayCommand implements ShouldQueue
{
    public $queue = 'foo';
    public $delay = 10;
}

class BusDispatcherTestSpecificQueueCommand implements ShouldQueue
{
    public $queue = 'high';
}

class BusDispatcherQueueable implements ShouldQueue
{
    use Queueable;
}

class StandAloneCommand
{
    //
}

class StandAloneHandler
{
    public function handle(StandAloneCommand $command)
    {
        return $command;
    }
}

class ShouldNotBeDispatched implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function handle()
    {
        throw new RuntimeException('This should not be run');
    }
}
