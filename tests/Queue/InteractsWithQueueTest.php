<?php

namespace Illuminate\Tests\Queue;

use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\InteractsWithQueue;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;

class InteractsWithQueueTest extends TestCase
{
    use VerifiesDoubles;

    public function testCreatesAnExceptionFromString()
    {
        $queueJob = TestDouble::for(Job::class);
        $queueJob->expects('fail')->with(Argument::satisfies(function ($e) {
            $this->assertInstanceOf(Exception::class, $e);
            $this->assertSame('Whoops!', $e->getMessage());

            return true;
        }));

        $job = new class
        {
            use InteractsWithQueue;

            public $job;
        };

        $job->job = $queueJob;
        $job->fail('Whoops!');
    }
}
