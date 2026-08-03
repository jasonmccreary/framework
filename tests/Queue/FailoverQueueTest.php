<?php

namespace Illuminate\Tests\Queue;

use JMac\Testing\TestDouble;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\FailoverQueue;
use Illuminate\Queue\QueueManager;
use PHPUnit\Framework\TestCase;

class FailoverQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_push_fails_over_on_exception()
    {
        $failover = new FailoverQueue($queue = TestDouble::for(QueueManager::class), $events = TestDouble::for(Dispatcher::class), [
            'redis',
            'sync',
        ]);

        $queue->shouldReceive('connection')->once()->with('redis')->andReturn(
            $redis = TestDouble::for('stdClass'),
        );

        $queue->shouldReceive('connection')->once()->with('sync')->andReturn(
            $sync = TestDouble::for('stdClass'),
        );

        $events->shouldReceive('dispatch')->once();

        $redis->shouldReceive('push')->once()->andReturnUsing(
            fn () => throw new \Exception('error')
        );

        $sync->shouldReceive('push')->once();

        $failover->push('some-job');
    }
}
