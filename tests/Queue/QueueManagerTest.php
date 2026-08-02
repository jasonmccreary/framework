<?php

namespace Illuminate\Tests\Queue;

use JMac\Testing\TestDouble;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Queue\QueueManager;
use PHPUnit\Framework\TestCase;
use stdClass;

class QueueManagerTest extends TestCase
{
    public function testDefaultConnectionCanBeResolved()
    {
        $app = [
            'config' => [
                'queue.default' => 'sync',
                'queue.connections.sync' => ['driver' => 'sync'],
            ],
            'encrypter' => $encrypter = TestDouble::for(Encrypter::class),
        ];

        $manager = new QueueManager($app);
        $connector = TestDouble::for(stdClass::class);
        $queue = TestDouble::for(stdClass::class);
        $queue->shouldReceive('setConnectionName')->once()->with('sync')->andReturnSelf();
        $connector->expects('connect')->with(['driver' => 'sync'])->returns($queue);
        $manager->addConnector('sync', function () use ($connector) {
            return $connector;
        });

        $queue->expects('setContainer')->with($app);
        $this->assertSame($queue, $manager->connection('sync'));
    }

    public function testOtherConnectionCanBeResolved()
    {
        $app = [
            'config' => [
                'queue.default' => 'sync',
                'queue.connections.foo' => ['driver' => 'bar'],
            ],
            'encrypter' => $encrypter = TestDouble::for(Encrypter::class),
        ];

        $manager = new QueueManager($app);
        $connector = TestDouble::for(stdClass::class);
        $queue = TestDouble::for(stdClass::class);
        $queue->shouldReceive('setConnectionName')->once()->with('foo')->andReturnSelf();
        $connector->expects('connect')->with(['driver' => 'bar'])->returns($queue);
        $manager->addConnector('bar', function () use ($connector) {
            return $connector;
        });
        $queue->expects('setContainer')->with($app);

        $this->assertSame($queue, $manager->connection('foo'));
    }

    public function testNullConnectionCanBeResolved()
    {
        $app = [
            'config' => [
                'queue.default' => 'null',
            ],
            'encrypter' => $encrypter = TestDouble::for(Encrypter::class),
        ];

        $manager = new QueueManager($app);
        $connector = TestDouble::for(stdClass::class);
        $queue = TestDouble::for(stdClass::class);
        $queue->shouldReceive('setConnectionName')->once()->with('null')->andReturnSelf();
        $connector->expects('connect')->with(['driver' => 'null'])->returns($queue);
        $manager->addConnector('null', function () use ($connector) {
            return $connector;
        });
        $queue->expects('setContainer')->with($app);

        $this->assertSame($queue, $manager->connection('null'));
    }

    public function testEnumConnectionCanBeResolved()
    {
        $app = [
            'config' => [
                'queue.default' => 'sync',
                'queue.connections.sync' => ['driver' => 'sync'],
            ],
            'encrypter' => $encrypter = TestDouble::for(Encrypter::class),
        ];

        $manager = new QueueManager($app);
        $connector = TestDouble::for(stdClass::class);
        $queue = TestDouble::for(stdClass::class);
        $queue->shouldReceive('setConnectionName')->once()->with('sync')->andReturnSelf();
        $connector->expects('connect')->with(['driver' => 'sync'])->returns($queue);
        $manager->addConnector('sync', function () use ($connector) {
            return $connector;
        });
        $queue->expects('setContainer')->with($app);

        $this->assertSame($queue, $manager->connection(QueueConnectionName::Sync));
    }

    public function testEnumConnectionCanBeChecked()
    {
        $app = [
            'config' => [
                'queue.default' => 'sync',
                'queue.connections.sync' => ['driver' => 'sync'],
            ],
            'encrypter' => $encrypter = TestDouble::for(Encrypter::class),
        ];

        $manager = new QueueManager($app);
        $connector = TestDouble::for(stdClass::class);
        $queue = TestDouble::for(stdClass::class);
        $queue->shouldReceive('setConnectionName')->once()->with('sync')->andReturnSelf();
        $connector->expects('connect')->with(['driver' => 'sync'])->returns($queue);
        $manager->addConnector('sync', function () use ($connector) {
            return $connector;
        });
        $queue->expects('setContainer')->with($app);

        $this->assertFalse($manager->connected(QueueConnectionName::Sync));
        $manager->connection(QueueConnectionName::Sync);
        $this->assertTrue($manager->connected(QueueConnectionName::Sync));
    }

    public function testSetDefaultDriverAcceptsBackedEnum()
    {
        $app = [
            'config' => [
                'queue.default' => 'sync',
                'queue.connections.sync' => ['driver' => 'sync'],
            ],
        ];

        $manager = new QueueManager($app);
        $manager->setDefaultDriver(QueueConnectionName::Sync);

        $this->assertSame('sync', $app['config']['queue.default']);
    }
}

enum QueueConnectionName: string
{
    case Sync = 'sync';
}
