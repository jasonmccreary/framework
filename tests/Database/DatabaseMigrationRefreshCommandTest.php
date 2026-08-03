<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Foundation\Application;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class DatabaseMigrationRefreshCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        RefreshCommand::prohibit(false);

        parent::tearDown();
    }

    public function testRefreshCommandCallsCommandsWithProperArguments()
    {
        $command = new RefreshCommand;

        $app = new ApplicationDatabaseRefreshStub(['path.database' => __DIR__]);
        $dispatcher = $app->instance(Dispatcher::class, $events = TestDouble::for(\stdClass::class));
        $console = TestDouble::for(ConsoleApplication::class)->passthru();
        $console->__construct();
        $command->setLaravel($app);
        $command->setApplication($console);

        $resetCommand = TestDouble::for(ResetCommand::class);
        $migrateCommand = TestDouble::for(MigrateCommand::class);

        $console->allows('find')->with('migrate:reset')->returns($resetCommand);
        $console->allows('find')->with('migrate')->returns($migrateCommand);
        $dispatcher->expects('dispatch')->with(m::type(DatabaseRefreshed::class));

        $quote = DIRECTORY_SEPARATOR === '\\' ? '"' : "'";
        $resetCommand->allows('run')->with(new InputMatcher("--force=1 {$quote}migrate:reset{$quote}"), m::any());
        $migrateCommand->allows('run')->with(new InputMatcher('--force=1 migrate'), m::any());

        $this->runCommand($command);
    }

    public function testRefreshCommandCallsCommandsWithStep()
    {
        $command = new RefreshCommand;

        $app = new ApplicationDatabaseRefreshStub(['path.database' => __DIR__]);
        $dispatcher = $app->instance(Dispatcher::class, $events = TestDouble::for(\stdClass::class));
        $console = TestDouble::for(ConsoleApplication::class)->passthru();
        $console->__construct();
        $command->setLaravel($app);
        $command->setApplication($console);

        $rollbackCommand = TestDouble::for(RollbackCommand::class);
        $migrateCommand = TestDouble::for(MigrateCommand::class);

        $console->allows('find')->with('migrate:rollback')->returns($rollbackCommand);
        $console->allows('find')->with('migrate')->returns($migrateCommand);
        $dispatcher->expects('dispatch')->with(m::type(DatabaseRefreshed::class));

        $quote = DIRECTORY_SEPARATOR === '\\' ? '"' : "'";
        $rollbackCommand->allows('run')->with(new InputMatcher("--step=2 --force=1 {$quote}migrate:rollback{$quote}"), m::any());
        $migrateCommand->allows('run')->with(new InputMatcher('--force=1 migrate'), m::any());

        $this->runCommand($command, ['--step' => 2]);
    }

    public function testRefreshCommandExitsWhenProhibited()
    {
        $command = new RefreshCommand;

        $app = new ApplicationDatabaseRefreshStub(['path.database' => __DIR__]);
        $dispatcher = $app->instance(Dispatcher::class, $events = TestDouble::for(\stdClass::class));
        $console = TestDouble::for(ConsoleApplication::class)->passthru();
        $console->__construct();
        $command->setLaravel($app);
        $command->setApplication($console);

        RefreshCommand::prohibit();

        $code = $this->runCommand($command);

        $this->assertSame(1, $code);

        $console->shouldNotHaveBeenCalled();
        $dispatcher->expects('dispatch')->never();
    }

    protected function runCommand($command, $input = [])
    {
        return $command->run(new ArrayInput($input), new NullOutput);
    }
}

class InputMatcher extends m\Matcher\MatcherAbstract
{
    /**
     * @param  \Symfony\Component\Console\Input\ArrayInput  $actual
     * @return bool
     */
    public function match(&$actual)
    {
        return (string) $actual == $this->_expected;
    }

    public function __toString()
    {
        return '';
    }
}

class ApplicationDatabaseRefreshStub extends Application
{
    public function __construct(array $data = [])
    {
        foreach ($data as $abstract => $instance) {
            $this->instance($abstract, $instance);
        }
    }

    public function environment(...$environments)
    {
        return 'development';
    }
}
