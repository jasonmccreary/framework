<?php

namespace Illuminate\Tests\Queue;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Console\ClearCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class QueueClearCommandTest extends TestCase
{
    public function testClearingDefaultQueue()
    {
        $queue = Double::for(ClearableQueue::class);
        $queue->expects('clear')->with('default')->returns(2);

        $output = $this->runClearCommand($queue);

        $this->assertStringContainsString('Cleared 2 jobs from the [default] queue', $output);
    }

    public function testClearingMultipleQueues()
    {
        $queue = Double::for(ClearableQueue::class);
        $queue->expects('clear')->with('high')->returns(3);
        $queue->expects('clear')->with('low')->returns(0);
        $queue->expects('clear')->with('emails')->returns(1);

        $output = $this->runClearCommand($queue, ['--queue' => 'high,low,emails']);

        $this->assertStringContainsString('Cleared 4 jobs from the [high, low, emails] queues', $output);
    }

    public function testClearingMultipleQueuesWithWhitespace()
    {
        $queue = Double::for(ClearableQueue::class);
        $queue->expects('clear')->with('high')->returns(3);
        $queue->expects('clear')->with('low')->returns(0);

        $output = $this->runClearCommand($queue, ['--queue' => 'high, low']);

        $this->assertStringContainsString('Cleared 3 jobs from the [high, low] queues', $output);
    }

    public function testClearingMultipleQueuesWithEmptyValues()
    {
        $queue = Double::for(ClearableQueue::class);
        $queue->expects('clear')->with('high')->returns(3);
        $queue->expects('clear')->with('low')->returns(0);

        $output = $this->runClearCommand($queue, ['--queue' => 'high,,low']);

        $this->assertStringContainsString('Cleared 3 jobs from the [high, low] queues', $output);
    }

    public function testClearingMultipleQueuesWithDuplicates()
    {
        $queue = Double::for(ClearableQueue::class);
        $queue->expects('clear')->with('high')->returns(3);
        $queue->expects('clear')->with('low')->returns(0);

        $output = $this->runClearCommand($queue, ['--queue' => 'high,low,high']);

        $this->assertStringContainsString('Cleared 3 jobs from the [high, low] queues', $output);
    }

    protected function runClearCommand($queue, array $arguments = []): string
    {
        $container = new Application;
        $container['env'] = 'testing';

        $config = Double::for(Repository::class, \ArrayAccess::class);
        $config->expects('offsetGet')->with('queue.default')->returns('redis');
        $config->allows('get')->with('queue.connections.redis.queue', 'default')->returns('default');

        $container['config'] = $config;

        $queueManager = Double::for(QueueManager::class);
        $queueManager->expects('connection')->with('redis')->returns($queue);

        $container['queue'] = $queueManager;

        $command = new ClearCommand;
        $command->setLaravel($container);

        $output = new BufferedOutput();
        $command->run(new ArrayInput($arguments), $output);

        return $output->fetch();
    }
}
