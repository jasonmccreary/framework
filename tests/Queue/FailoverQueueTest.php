<?php

namespace Illuminate\Tests\Queue;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Double;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Attributes\Delay;
use Illuminate\Queue\FailoverQueue;
use Illuminate\Queue\QueueManager;
use PHPUnit\Framework\TestCase;

class FailoverQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_push_fails_over_on_exception()
    {
        $queue = Double::for(QueueManager::class);
        $events = Double::for(Dispatcher::class);
        $failover = new FailoverQueue($queue, $events, [
            'redis',
            'sync',
        ]);

        $redis = Double::for('stdClass');
        $queue->expects('connection')->with('redis')->returns($redis);

        $sync = Double::for('stdClass');
        $queue->expects('connection')->with('sync')->returns($sync);

        $events->expects('dispatch');

        $redis->expects('push')->resolves(fn () => throw new \Exception('error'));

        $sync->expects('push');

        $failover->push('some-job');
    }

    public function test_bulk_respects_job_delays()
    {
        $queue = Double::for(QueueManager::class);
        $failover = new FailoverQueue($queue, Double::for(Dispatcher::class), ['sync']);

        $sync = Double::for('stdClass');
        $queue->expects('connection')->times(3)->with('sync')->returns($sync);

        $sync->expects('later')->with(15, Argument::type(FailoverJobWithDelayAttribute::class), '', null);
        $sync->expects('later')->with(30, Argument::type(FailoverJobWithDelayProperty::class), '', null);
        $sync->expects('push')->with('regular-job', '', null);

        $failover->bulk([new FailoverJobWithDelayAttribute, new FailoverJobWithDelayProperty, 'regular-job']);
    }
}

#[Delay(15)]
class FailoverJobWithDelayAttribute
{
}

class FailoverJobWithDelayProperty
{
    public $delay = 30;
}
