<?php

namespace Illuminate\Tests\Queue;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\BeanstalkdJob;
use Illuminate\Queue\Jobs\Job;
use Mockery as m;
use Pheanstalk\Contract\JobIdInterface;
use Pheanstalk\Contract\PheanstalkManagerInterface;
use Pheanstalk\Contract\PheanstalkPublisherInterface;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Pheanstalk\Pheanstalk;
use PHPUnit\Framework\TestCase;
use stdClass;

class QueueBeanstalkdJobTest extends TestCase
{
    public function testFireProperlyCallsTheJobHandler()
    {
        $job = $this->getJob();
        $job->getPheanstalkJob()->expects('getData')->returns(json_encode(['job' => 'foo', 'data' => ['data']]));
        $job->getContainer()->expects('make')->with('foo')->returns($handler = TestDouble::for(stdClass::class));
        $handler->expects('fire')->with($job, ['data']);

        $job->fire();
    }

    public function testFailProperlyCallsTheJobHandler()
    {
        $job = $this->getJob();
        $job->getPheanstalkJob()->allows('getData')->returns(json_encode(['job' => 'foo', 'uuid' => 'test-uuid', 'data' => ['data']]));
        $job->getContainer()->expects('make')->with('foo')->returns($handler = TestDouble::for(BeanstalkdJobTestFailedTest::class));
        $job->getPheanstalk()->expects('delete')->with($job->getPheanstalkJob())->returns($job->getPheanstalk());
        $handler->expects('failed')->with(['data'], Argument::type(Exception::class), 'test-uuid', Argument::type(Job::class));
        $job->getContainer()->expects('make')->with(Dispatcher::class)->returns($events = TestDouble::for(Dispatcher::class));
        $events->expects('dispatch')->with(Argument::type(JobFailed::class))->returns(null);

        $job->fail(new Exception);
    }

    public function testDeleteRemovesTheJobFromBeanstalkd()
    {
        $job = $this->getJob();
        $job->getPheanstalk()->expects('delete')->with($job->getPheanstalkJob());

        $job->delete();
    }

    public function testReleaseProperlyReleasesJobOntoBeanstalkd()
    {
        $job = $this->getJob();
        $job->getPheanstalk()->expects('release')->with($job->getPheanstalkJob(), Pheanstalk::DEFAULT_PRIORITY, 0);

        $job->release();
    }

    public function testBuryProperlyBuryTheJobFromBeanstalkd()
    {
        $job = $this->getJob();
        $job->getPheanstalk()->expects('bury')->with($job->getPheanstalkJob());

        $job->bury();
    }

    protected function getJob()
    {
        return new BeanstalkdJob(
            TestDouble::for(Container::class),
            TestDouble::for(implode(',', [PheanstalkManagerInterface::class, PheanstalkPublisherInterface::class, PheanstalkSubscriberInterface::class])),
            TestDouble::for(JobIdInterface::class),
            'connection-name',
            'default'
        );
    }
}

class BeanstalkdJobTestFailedTest
{
    public function failed(array $data)
    {
        //
    }
}
