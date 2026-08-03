<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Database\Seeder;
use Mockery as m;
use Mockery\Mock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class TestSeeder extends Seeder
{
    public function run()
    {
        //
    }
}

class TestDepsSeeder extends Seeder
{
    public function run(Mock $someDependency, $someParam = '')
    {
        //
    }
}

class DatabaseSeederTest extends TestCase
{
    public function testCallResolveTheClassAndCallsRun()
    {
        $seeder = new TestSeeder;
        $seeder->setContainer($container = TestDouble::for(Container::class));
        $output = TestDouble::for(OutputInterface::class);
        $output->expects('writeln')->times(3);
        $command = TestDouble::for(Command::class);
        $command->expects('getOutput')->times(3)->returns($output);
        $seeder->setCommand($command);
        $container->expects('make')->with('ClassName')->returns($child = TestDouble::for(Seeder::class));
        $child->expects('setContainer')->with($container)->returns($child);
        $child->expects('setCommand')->with($command)->returns($child);
        $child->expects('__invoke');

        $seeder->call('ClassName');
    }

    public function testSetContainer()
    {
        $seeder = new TestSeeder;
        $container = TestDouble::for(Container::class);
        $this->assertEquals($seeder->setContainer($container), $seeder);
    }

    public function testSetCommand()
    {
        $seeder = new TestSeeder;
        $command = TestDouble::for(Command::class);
        $this->assertEquals($seeder->setCommand($command), $seeder);
    }

    public function testInjectDependenciesOnRunMethod()
    {
        $container = TestDouble::for(Container::class);
        $container->allows('call');

        $seeder = new TestDepsSeeder;
        $seeder->setContainer($container);

        $seeder->__invoke();

        $container->shouldHaveReceived('call')->once()->with([$seeder, 'run'], []);
    }

    public function testSendParamsOnCallMethodWithDeps()
    {
        $container = TestDouble::for(Container::class);
        $container->allows('call');

        $seeder = new TestDepsSeeder;
        $seeder->setContainer($container);

        $seeder->__invoke(['test1', 'test2']);

        $container->shouldHaveReceived('call')->once()->with([$seeder, 'run'], ['test1', 'test2']);
    }
}
