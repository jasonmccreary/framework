<?php

namespace Illuminate\Tests\Redis;

use JMac\Testing\Matching\Argument;
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
        $client->allows('get')->with('key')->throws($exception);

        $events = TestDouble::for(Dispatcher::class);
        $events->expects('dispatch')->with(Argument::satisfies(function ($event) use ($exception) {
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
        $client->allows('get')->with('key')->throws($exception);

        $events = TestDouble::for(Dispatcher::class);
        $events->expects('dispatch')->with(Argument::type(CommandFailed::class));
        $events->expects('dispatch')->with(Argument::type(CommandExecuted::class))->never();

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
        $client->allows('get')->with('key')->throws($exception);

        $events = TestDouble::for(Dispatcher::class);
        $events->expects('dispatch')->with(Argument::satisfies(function ($event) {
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
        $events->expects('listen')->with(CommandFailed::class, Argument::type('Closure'));

        $connection = new PhpRedisConnection($client);
        $connection->setEventDispatcher($events);

        $connection->listenForFailures(function () {
            // callback
        });
    }
}
