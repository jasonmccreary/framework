<?php

namespace Illuminate\Tests\Queue;

use JMac\Testing\TestDouble;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\RedisQueue;
use PHPUnit\Framework\TestCase;
use stdClass;

class QueueRedisJobTest extends TestCase
{
    public function testFireProperlyCallsTheJobHandler()
    {
        $job = $this->getJob();
        $job->getContainer()->shouldReceive('make')->once()->with('foo')->andReturn($handler = TestDouble::for(stdClass::class));
        $handler->shouldReceive('fire')->once()->with($job, ['data']);

        $job->fire();
    }

    public function testDeleteRemovesTheJobFromRedis()
    {
        $job = $this->getJob();
        $job->getRedisQueue()->shouldReceive('deleteReserved')->once()
            ->with('default', $job);

        $job->delete();
    }

    public function testReleaseProperlyReleasesJobOntoRedis()
    {
        $job = $this->getJob();
        $job->getRedisQueue()->shouldReceive('deleteAndRelease')->once()
            ->with('default', $job, 1);

        $job->release(1);
    }

    protected function getJob()
    {
        return new RedisJob(
            TestDouble::for(Container::class),
            TestDouble::for(RedisQueue::class),
            json_encode(['job' => 'foo', 'data' => ['data'], 'attempts' => 1]),
            json_encode(['job' => 'foo', 'data' => ['data'], 'attempts' => 2]),
            'connection-name',
            'default'
        );
    }
}
