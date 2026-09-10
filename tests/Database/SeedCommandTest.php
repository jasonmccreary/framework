<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Double;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Events\NullDispatcher;
use Illuminate\Testing\Assert;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class SeedCommandTest extends TestCase
{
    public function testHandle()
    {
        $input = new ArrayInput(['--force' => true, '--database' => 'sqlite']);
        $output = new NullOutput;
        $outputStyle = new OutputStyle($input, $output);

        $seeder = Double::for(Seeder::class);
        $seeder->expects('setContainer')->returns($seeder);
        $seeder->expects('setCommand')->returns($seeder);
        $seeder->expects('__invoke');

        $resolver = Double::for(ConnectionResolverInterface::class);
        $resolver->expects('getDefaultConnection');
        $resolver->expects('setDefaultConnection')->with('sqlite');

        $container = Double::for(Container::class);
        $container->expects('call');
        $container->expects('environment')->returns('testing');
        $container->allows('runningUnitTests')->returns('true');
        $container->expects('make')->with('DatabaseSeeder')->returns($seeder);
        $container->expects('make')->with(OutputStyle::class, Argument::any())->returns($outputStyle);
        $container->expects('make')->with(Factory::class, Argument::any())->returns(new Factory($outputStyle));

        $command = new SeedCommand($resolver);
        $command->setLaravel($container);

        // call run to set up IO, then fire manually.
        $command->run($input, $output);
        $command->handle();

        $container->shouldHaveReceived('call')->with([$command, 'handle']);
    }

    public function testFailedSeederRestoresPreviousDefaultConnection()
    {
        $input = new ArrayInput(['--force' => true, '--database' => 'sqlite']);
        $output = new NullOutput;
        $outputStyle = new OutputStyle($input, $output);

        $seeder = Double::for(Seeder::class);
        $seeder->expects('setContainer')->returns($seeder);
        $seeder->expects('setCommand')->returns($seeder);
        $seeder->expects('__invoke')->throws(new RuntimeException('Seeding failed.'));

        $connections = [];

        $resolver = Double::for(ConnectionResolverInterface::class);
        $resolver->expects('getDefaultConnection')->returns('mysql');
        $resolver->allows('setDefaultConnection')->resolves(function ($name) use (&$connections) {
            $connections[] = $name;
        });

        $container = Double::for(Container::class);
        $container->expects('call');
        $container->expects('environment')->returns('testing');
        $container->allows('runningUnitTests')->returns('true');
        $container->expects('make')->with('DatabaseSeeder')->returns($seeder);
        $container->expects('make')->with(OutputStyle::class, Argument::any())->returns($outputStyle);
        $container->expects('make')->with(Factory::class, Argument::any())->returns(new Factory($outputStyle));

        $command = new SeedCommand($resolver);
        $command->setLaravel($container);

        // call run to set up IO, then fire manually.
        $command->run($input, $output);

        try {
            $command->handle();
            $this->fail('Seeding should have failed.');
        } catch (RuntimeException) {
            //
        }

        Assert::assertSame(['sqlite', 'mysql'], $connections);
    }

    public function testWithoutModelEvents()
    {
        $input = new ArrayInput([
            '--force' => true,
            '--database' => 'sqlite',
            '--class' => UserWithoutModelEventsSeeder::class,
        ]);
        $output = new NullOutput;
        $outputStyle = new OutputStyle($input, $output);

        $instance = new UserWithoutModelEventsSeeder();

        $seeder = Double::for($instance);
        $seeder->expects('setContainer')->returns($seeder);
        $seeder->expects('setCommand')->returns($seeder);

        $resolver = Double::for(ConnectionResolverInterface::class);
        $resolver->expects('getDefaultConnection');
        $resolver->expects('setDefaultConnection')->with('sqlite');

        $container = Double::for(Container::class);
        $container->expects('call');
        $container->expects('environment')->returns('testing');
        $container->allows('runningUnitTests')->returns('true');
        $container->expects('make')->with(UserWithoutModelEventsSeeder::class)->returns($seeder);
        $container->expects('make')->with(OutputStyle::class, Argument::any())->returns($outputStyle);
        $container->expects('make')->with(Factory::class, Argument::any())->returns(new Factory($outputStyle));

        $command = new SeedCommand($resolver);
        $command->setLaravel($container);

        $dispatcher = Double::for(Dispatcher::class);
        Model::setEventDispatcher($dispatcher);

        // call run to set up IO, then fire manually.
        $command->run($input, $output);
        $command->handle();

        Assert::assertSame($dispatcher, Model::getEventDispatcher());

        $container->shouldHaveReceived('call')->with([$command, 'handle']);
    }

    public function testProhibitable()
    {
        $input = new ArrayInput([]);
        $output = new NullOutput;
        $outputStyle = new OutputStyle($input, $output);

        $resolver = Double::for(ConnectionResolverInterface::class);

        $container = Double::for(Container::class);
        $container->expects('call');
        $container->allows('runningUnitTests')->returns('true');
        $container->expects('make')->with(OutputStyle::class, Argument::any())->returns($outputStyle);
        $container->expects('make')->with(Factory::class, Argument::any())->returns(new Factory($outputStyle));

        $command = new SeedCommand($resolver);
        $command->setLaravel($container);

        // call run to set up IO, then fire manually.
        $command->run($input, $output);

        SeedCommand::prohibit();

        Assert::assertSame(Command::FAILURE, $command->handle());
    }

    protected function tearDown(): void
    {
        SeedCommand::prohibit(false);

        Model::unsetEventDispatcher();
    }
}

class UserWithoutModelEventsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run()
    {
        Assert::assertInstanceOf(NullDispatcher::class, Model::getEventDispatcher());
    }
}
