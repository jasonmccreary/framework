<?php

namespace Illuminate\Tests\Integration\Queue;

use JMac\Testing\TestDouble;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;

class CallQueuedHandlerTest extends TestCase
{
    public function testJobCanBeDispatched()
    {
        CallQueuedHandlerTestJob::$handled = false;

        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $job = TestDouble::for(Job::class);
        $job->allows('hasFailed')->returns(false);
        $job->allows('isDeleted')->returns(false);
        $job->allows('isReleased')->returns(false);
        $job->allows('isDeletedOrReleased')->returns(false);
        $job->expects('delete');

        $instance->call($job, [
            'command' => serialize(new CallQueuedHandlerTestJob),
        ]);

        $this->assertTrue(CallQueuedHandlerTestJob::$handled);
    }

    public function testJobCanBeDispatchedThroughMiddleware()
    {
        CallQueuedHandlerTestJobWithMiddleware::$handled = false;
        CallQueuedHandlerTestJobWithMiddleware::$middlewareCommand = null;

        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $job = TestDouble::for(Job::class);
        $job->allows('hasFailed')->returns(false);
        $job->allows('isDeleted')->returns(false);
        $job->allows('isReleased')->returns(false);
        $job->allows('isDeletedOrReleased')->returns(false);
        $job->expects('delete');

        $instance->call($job, [
            'command' => serialize($command = new CallQueuedHandlerTestJobWithMiddleware),
        ]);

        $this->assertInstanceOf(CallQueuedHandlerTestJobWithMiddleware::class, CallQueuedHandlerTestJobWithMiddleware::$middlewareCommand);
        $this->assertTrue(CallQueuedHandlerTestJobWithMiddleware::$handled);
    }

    public function testJobCanBeDispatchedThroughMiddlewareOnDispatch()
    {
        $_SERVER['__test.dispatchMiddleware'] = false;
        CallQueuedHandlerTestJobWithMiddleware::$handled = false;
        CallQueuedHandlerTestJobWithMiddleware::$middlewareCommand = null;

        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $job = TestDouble::for(Job::class);
        $job->allows('hasFailed')->returns(false);
        $job->allows('isDeleted')->returns(false);
        $job->allows('isReleased')->returns(false);
        $job->allows('isDeletedOrReleased')->returns(false);
        $job->expects('delete');

        $command = $command = new CallQueuedHandlerTestJobWithMiddleware;
        $command->through([new TestJobMiddleware]);

        $instance->call($job, [
            'command' => serialize($command),
        ]);

        $this->assertInstanceOf(CallQueuedHandlerTestJobWithMiddleware::class, CallQueuedHandlerTestJobWithMiddleware::$middlewareCommand);
        $this->assertTrue(CallQueuedHandlerTestJobWithMiddleware::$handled);
        $this->assertTrue($_SERVER['__test.dispatchMiddleware']);
    }

    public function testJobIsMarkedAsFailedIfModelNotFoundExceptionIsThrown()
    {
        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $job = TestDouble::for(Job::class);
        $job->allows('payload')->returns(['deleteWhenMissingModels' => false]);
        $job->expects('fail');

        $instance->call($job, [
            'command' => serialize(new CallQueuedHandlerExceptionThrowerWithoutDelete),
        ]);
    }

    public function testJobIsDeletedIfHasDeleteProperty()
    {
        Event::fake();

        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $job = TestDouble::for(Job::class);
        $job->allows('payload')->returns(['deleteWhenMissingModels' => true]);
        $job->allows('getConnectionName')->returns('connection');
        $job->allows('resolveQueuedJobClass')->returns(CallQueuedHandlerExceptionThrower::class);
        $job->expects('markAsFailed')->never();
        $job->allows('isDeleted')->returns(false);
        $job->expects('delete');
        $job->expects('failed')->never();

        $instance->call($job, [
            'command' => serialize(new CallQueuedHandlerExceptionThrower),
        ]);

        Event::assertNotDispatched(JobFailed::class);
    }

    public function testJobIsDeletedIfHasDeleteAttribute()
    {
        Event::fake();

        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $job = TestDouble::for(Job::class);
        $job->allows('payload')->returns(['deleteWhenMissingModels' => true]);
        $job->allows('getConnectionName')->returns('connection');
        $job->allows('resolveQueuedJobClass')->returns(CallQueuedHandlerAttributeExceptionThrower::class);
        $job->expects('markAsFailed')->never();
        $job->allows('isDeleted')->returns(false);
        $job->expects('delete');
        $job->expects('failed')->never();

        $instance->call($job, [
            'command' => serialize(new CallQueuedHandlerAttributeExceptionThrower()),
        ]);

        Event::assertNotDispatched(JobFailed::class);
    }

    public function testBatchJobIsRecordedWhenDeletedDueToMissingModel()
    {
        Event::fake();

        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $batch = TestDouble::for(Batch::class);
        $batch->expects('recordSuccessfulJob')->with('job-uuid');

        $repository = TestDouble::for(BatchRepository::class);
        $repository->expects('find')->with('test-batch-id')->returns($batch);
        $this->app->instance(BatchRepository::class, $repository);

        $serialized = serialize((new CallQueuedHandlerBatchableExceptionThrower)->withBatchId('test-batch-id'));

        $job = TestDouble::for(Job::class);
        $job->allows('resolveQueuedJobClass')->returns(CallQueuedHandlerBatchableExceptionThrower::class);
        $job->expects('markAsFailed')->never();
        $job->allows('isDeleted')->returns(false);
        $job->expects('delete');
        $job->expects('failed')->never();
        $job->allows('uuid')->returns('job-uuid');
        $job->allows('payload')->returns([
            'deleteWhenMissingModels' => true,
            'data' => [
                'batchId' => 'test-batch-id',
                'command' => $serialized,
            ],
        ]);

        $instance->call($job, [
            'command' => $serialized,
        ]);

        Event::assertNotDispatched(JobFailed::class);
    }
}

class CallQueuedHandlerTestJob
{
    use InteractsWithQueue;

    public static $handled = false;

    public function handle()
    {
        static::$handled = true;
    }
}

/** This exists to test that middleware can also be defined in base classes */
abstract class AbstractCallQueuedHandlerTestJobWithMiddleware
{
    public static $middlewareCommand;

    public function middleware()
    {
        return [
            new class
            {
                public function handle($command, $next)
                {
                    AbstractCallQueuedHandlerTestJobWithMiddleware::$middlewareCommand = $command;

                    return $next($command);
                }
            },
        ];
    }
}

class CallQueuedHandlerTestJobWithMiddleware extends AbstractCallQueuedHandlerTestJobWithMiddleware
{
    use InteractsWithQueue, Queueable;

    public static $handled = false;

    public function handle()
    {
        static::$handled = true;
    }
}

class CallQueuedHandlerExceptionThrower
{
    public $deleteWhenMissingModels = true;

    public function handle()
    {
        //
    }

    public function __wakeup()
    {
        throw new ModelNotFoundException('Foo');
    }
}

class CallQueuedHandlerExceptionThrowerWithoutDelete
{
    public function handle()
    {
        //
    }

    public function __wakeup()
    {
        throw new ModelNotFoundException('Foo');
    }
}

#[DeleteWhenMissingModels]
class CallQueuedHandlerAttributeExceptionThrower
{
    public function handle()
    {
        //
    }

    public function __wakeup()
    {
        throw new ModelNotFoundException('Foo');
    }
}

#[DeleteWhenMissingModels]
class CallQueuedHandlerBatchableExceptionThrower
{
    use Batchable, InteractsWithQueue;

    public function handle()
    {
        //
    }

    public function __wakeup()
    {
        throw new ModelNotFoundException('Foo');
    }
}

class TestJobMiddleware
{
    public function handle($command, $next)
    {
        $_SERVER['__test.dispatchMiddleware'] = true;

        return $next($command);
    }
}
