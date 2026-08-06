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

        $queue->expects('connection')->with('redis')->returns($redis = TestDouble::for('stdClass'));

        $queue->expects('connection')->with('sync')->returns($sync = TestDouble::for('stdClass'));

        $events->expects('dispatch');

        $redis->expects('push')->resolves(fn () => throw new \Exception('error'));

        $sync->expects('push');

        $failover->push('some-job');
    }
}
