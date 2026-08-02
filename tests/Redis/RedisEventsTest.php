<?php

namespace Illuminate\Tests\Redis;

use JMac\Testing\TestDouble;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Redis\Events\CommandFailed;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisEventsTest extends TestCase
{
    public function testCommandFailedEventIsDispatched()
    {
        $exception = new Exception('Test exception');

        $client = TestDouble::for(Redis::class);
        $client->shouldReceive('get')->with('key')->andThrow($exception);

        $events = TestDouble::for(Dispatcher::class);
        $events->shouldReceive('dispatch')->once()->with(m::on(function ($event) use ($exception) {
            return $event instanceof CommandFailed
                && $event->command === 'get'
                && $event->parameters === ['key']
                && $event->exception === $exception;
        }));

        $connection = new PhpRedisConnection($client);
        $connection->setEventDispatcher($events);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Test exception');

        $connection->command('get', ['key']);
    }

    public function testCommandExecutedEventIsNotDispatchedWhenCommandFails()
    {
        $exception = new Exception('Test exception');

        $client = TestDouble::for(Redis::class);
        $client->shouldReceive('get')->with('key')->andThrow($exception);

        $events = TestDouble::for(Dispatcher::class);
        $events->shouldReceive('dispatch')->once()->with(m::type(CommandFailed::class));
        $events->shouldNotReceive('dispatch')->with(m::type(CommandExecuted::class));

        $connection = new PhpRedisConnection($client);
        $connection->setEventDispatcher($events);

        try {
            $connection->command('get', ['key']);
        } catch (Exception) {
            // Expected exception
        }
    }

    public function testCommandFailedEventContainsConnectionName()
    {
        $exception = new Exception('Test exception');

        $client = TestDouble::for(Redis::class);
        $client->shouldReceive('get')->with('key')->andThrow($exception);

        $events = TestDouble::for(Dispatcher::class);
        $events->shouldReceive('dispatch')->once()->with(m::on(function ($event) {
            return $event instanceof CommandFailed
                && $event->connectionName === 'test-connection';
        }));

        $connection = new PhpRedisConnection($client);
        $connection->setName('test-connection');
        $connection->setEventDispatcher($events);

        try {
            $connection->command('get', ['key']);
        } catch (Exception) {
            // Expected exception
        }
    }

    public function testListenForFailuresRegistersCallback()
    {
        $client = TestDouble::for(Redis::class);

        $events = TestDouble::for(Dispatcher::class);
        $events->shouldReceive('listen')->once()->with(CommandFailed::class, m::type('Closure'));

        $connection = new PhpRedisConnection($client);
        $connection->setEventDispatcher($events);

        $connection->listenForFailures(function () {
            // callback
        });
    }
}
