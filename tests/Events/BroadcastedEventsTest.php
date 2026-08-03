<?php

namespace Illuminate\Tests\Events;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use Illuminate\Broadcasting\PendingBroadcast;
use Illuminate\Container\Container;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class BroadcastedEventsTest extends TestCase
{
    public function testShouldBroadcastSuccess()
    {
        $d = TestDouble::for(Dispatcher::class);

        $d->passthru()->shouldAllowMockingProtectedMethods();

        $event = new BroadcastEvent;

        $this->assertTrue($d->shouldBroadcast([$event]));

        $event = new AlwaysBroadcastEvent;

        $this->assertTrue($d->shouldBroadcast([$event]));
    }

    public function testShouldBroadcastAsQueuedAndCallNormalListeners()
    {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher($container = TestDouble::for(Container::class));
        $broadcast = TestDouble::for(BroadcastFactory::class);
        $broadcast->expects('queue');
        $container->expects('make')->with(BroadcastFactory::class)->returns($broadcast);

        $d->listen(AlwaysBroadcastEvent::class, function ($payload) {
            $_SERVER['__event.test'] = $payload;
        });

        $d->dispatch($e = new AlwaysBroadcastEvent);

        $this->assertSame($e, $_SERVER['__event.test']);
    }

    public function testShouldBroadcastFail()
    {
        $d = TestDouble::for(Dispatcher::class);

        $d->passthru()->shouldAllowMockingProtectedMethods();

        $event = new BroadcastFalseCondition;

        $this->assertFalse($d->shouldBroadcast([$event]));

        $event = new ExampleEvent;

        $this->assertFalse($d->shouldBroadcast([$event]));
    }

    public function testBroadcastWithMultipleChannels()
    {
        $d = new Dispatcher($container = TestDouble::for(Container::class));
        $broadcast = TestDouble::for(BroadcastFactory::class);
        $broadcast->expects('queue');
        $container->expects('make')->with(BroadcastFactory::class)->returns($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public function broadcastOn()
            {
                return ['channel-1', 'channel-2'];
            }
        };

        $d->dispatch($event);
    }

    public function testBroadcastWithCustomConnectionName()
    {
        $d = new Dispatcher($container = TestDouble::for(Container::class));
        $broadcast = TestDouble::for(BroadcastFactory::class);
        $broadcast->expects('queue');
        $container->expects('make')->with(BroadcastFactory::class)->returns($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public $connection = 'custom-connection';

            public function broadcastOn()
            {
                return ['test-channel'];
            }
        };

        $d->dispatch($event);
    }

    public function testBroadcastWithCustomEventName()
    {
        $d = new Dispatcher($container = TestDouble::for(Container::class));
        $broadcast = TestDouble::for(BroadcastFactory::class);
        $broadcast->expects('queue');
        $container->expects('make')->with(BroadcastFactory::class)->returns($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public function broadcastOn()
            {
                return ['test-channel'];
            }

            public function broadcastAs()
            {
                return 'custom-event-name';
            }
        };

        $d->dispatch($event);
    }

    public function testBroadcastWithCustomPayload()
    {
        $d = new Dispatcher($container = TestDouble::for(Container::class));
        $broadcast = TestDouble::for(BroadcastFactory::class);
        $broadcast->expects('queue');
        $container->expects('make')->with(BroadcastFactory::class)->returns($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public $customData = 'test-data';

            public function broadcastOn()
            {
                return ['test-channel'];
            }

            public function broadcastWith()
            {
                return ['custom' => $this->customData];
            }
        };

        $d->dispatch($event);
    }

    public function testEventBroadcastsUsingNamedArguments()
    {
        $container = new Container;
        $broadcast = TestDouble::for(BroadcastFactory::class);
        $container->instance(BroadcastFactory::class, $broadcast);

        $originalContainer = Container::getInstance();
        Container::setInstance($container);

        try {
            $pendingBroadcast = TestDouble::for(PendingBroadcast::class);

            $broadcast->expects('event')->with(Argument::satisfies(function ($event) {
                    $this->assertInstanceOf(BroadcastableNamedArgumentsEvent::class, $event);
                    $this->assertSame('first-value', $event->first);
                    $this->assertSame('second-value', $event->second);

                    return true;
                }))->returns($pendingBroadcast);

            $this->assertSame(
                $pendingBroadcast,
                BroadcastableNamedArgumentsEvent::broadcast(second: 'second-value', first: 'first-value')
            );
        } finally {
            Container::setInstance($originalContainer);
        }
    }
}

class BroadcastEvent implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return ['test-channel'];
    }

    public function broadcastWhen()
    {
        return true;
    }
}

class AlwaysBroadcastEvent implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return ['test-channel'];
    }
}

class BroadcastFalseCondition extends BroadcastEvent
{
    public function broadcastWhen()
    {
        return false;
    }
}

class BroadcastableNamedArgumentsEvent
{
    use \Illuminate\Foundation\Events\Dispatchable;

    public function __construct(
        public string $first,
        public string $second,
    ) {
    }
}
