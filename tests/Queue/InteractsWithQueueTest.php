<?php

namespace Illuminate\Tests\Queue;

use JMac\Testing\TestDouble;
use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\InteractsWithQueue;
use PHPUnit\Framework\TestCase;

class InteractsWithQueueTest extends TestCase
{
    public function testCreatesAnExceptionFromString()
    {
        $queueJob = TestDouble::for(Job::class);
        $queueJob->shouldReceive('fail')->withArgs(function ($e) {
            $this->assertInstanceOf(Exception::class, $e);
            $this->assertSame('Whoops!', $e->getMessage());

            return true;
        });

        $job = new class
        {
            use InteractsWithQueue;

            public $job;
        };

        $job->job = $queueJob;
        $job->fail('Whoops!');
    }
}
