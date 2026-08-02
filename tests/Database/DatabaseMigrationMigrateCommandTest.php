<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Console\CommandMutex;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Events\SchemaLoaded;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class DatabaseMigrationMigrateCommandTest extends TestCase
{
    public function testBasicMigrationsCallMigratorWithProperArguments()
    {
        $command = new MigrateCommand($migrator = TestDouble::for(Migrator::class), $dispatcher = TestDouble::for(Dispatcher::class));
        $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
        $app->useDatabasePath(__DIR__);
        $command->setLaravel($app);
        $migrator->expects('paths')->returns([]);
        $migrator->allows('hasRunAnyMigrations')->returns(true);
        $migrator->expects('usingConnection')->resolves(function ($name, $callback) {
            return $callback();
        });
        $migrator->expects('setOutput')->returns($migrator);
        $migrator->expects('run')->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
        $migrator->allows('getNotes')->returns([]);
        $migrator->expects('repositoryExists')->returns(true);

        $this->runCommand($command);
    }

    public function testMigrationsCanBeRunWithStoredSchema()
    {
        $command = new MigrateCommand($migrator = TestDouble::for(Migrator::class), $dispatcher = TestDouble::for(Dispatcher::class));
        $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
        $app->useDatabasePath(__DIR__);
        $command->setLaravel($app);
        $migrator->expects('paths')->returns([]);
        $migrator->allows('hasRunAnyMigrations')->returns(false);
        $migrator->allows('resolveConnection')->returns($connection = TestDouble::for(stdClass::class));
        $connection->allows('getName')->returns('mysql');
        $migrator->expects('usingConnection')->resolves(function ($name, $callback) {
            return $callback();
        });
        $migrator->expects('deleteRepository');
        $connection->allows('getSchemaState')->returns($schemaState = TestDouble::for(stdClass::class));
        $schemaState->shouldReceive('handleOutputUsing')->andReturnSelf();
        $schemaState->expects('load')->with(__DIR__.'/stubs/schema.sql');
        $dispatcher->expects('dispatch')->with(m::type(SchemaLoaded::class));
        $migrator->expects('setOutput')->returns($migrator);
        $migrator->expects('run')->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
        $migrator->allows('getNotes')->returns([]);
        $migrator->expects('repositoryExists')->returns(true);

        $this->runCommand($command, ['--schema-path' => __DIR__.'/stubs/schema.sql']);
    }

    public function testMigrationRepositoryCreatedWhenNecessary()
    {
        $params = [$migrator = TestDouble::for(Migrator::class), $dispatcher = TestDouble::for(Dispatcher::class)];
        $command = $this->getMockBuilder(MigrateCommand::class)->onlyMethods(['callSilent'])->setConstructorArgs($params)->getMock();
        $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
        $app->useDatabasePath(__DIR__);
        $command->setLaravel($app);
        $migrator->expects('paths')->returns([]);
        $migrator->allows('hasRunAnyMigrations')->returns(true);
        $migrator->expects('usingConnection')->resolves(function ($name, $callback) {
            return $callback();
        });
        $migrator->expects('setOutput')->returns($migrator);
        $migrator->expects('run')->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
        $migrator->expects('repositoryExists')->returns(false);
        $command->expects($this->once())->method('callSilent')->with('migrate:install', []);

        $this->runCommand($command);
    }

    public function testTheCommandMayBePretended()
    {
        $command = new MigrateCommand($migrator = TestDouble::for(Migrator::class), $dispatcher = TestDouble::for(Dispatcher::class));
        $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
        $app->useDatabasePath(__DIR__);
        $command->setLaravel($app);
        $migrator->expects('paths')->returns([]);
        $migrator->allows('hasRunAnyMigrations')->returns(true);
        $migrator->expects('usingConnection')->resolves(function ($name, $callback) {
            return $callback();
        });
        $migrator->expects('setOutput')->returns($migrator);
        $migrator->expects('run')->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => true, 'step' => false]);
        $migrator->expects('repositoryExists')->returns(true);

        $this->runCommand($command, ['--pretend' => true]);
    }

    public function testTheDatabaseMayBeSet()
    {
        $command = new MigrateCommand($migrator = TestDouble::for(Migrator::class), $dispatcher = TestDouble::for(Dispatcher::class));
        $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
        $app->useDatabasePath(__DIR__);
        $command->setLaravel($app);
        $migrator->expects('paths')->returns([]);
        $migrator->allows('hasRunAnyMigrations')->returns(true);
        $migrator->expects('usingConnection')->resolves(function ($name, $callback) {
            return $callback();
        });
        $migrator->expects('setOutput')->returns($migrator);
        $migrator->expects('run')->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => false]);
        $migrator->expects('repositoryExists')->returns(true);

        $this->runCommand($command, ['--database' => 'foo']);
    }

    public function testStepMayBeSet()
    {
        $command = new MigrateCommand($migrator = TestDouble::for(Migrator::class), $dispatcher = TestDouble::for(Dispatcher::class));
        $app = new ApplicationDatabaseMigrationStub(['path.database' => __DIR__]);
        $app->useDatabasePath(__DIR__);
        $command->setLaravel($app);
        $migrator->expects('paths')->returns([]);
        $migrator->allows('hasRunAnyMigrations')->returns(true);
        $migrator->expects('usingConnection')->resolves(function ($name, $callback) {
            return $callback();
        });
        $migrator->expects('setOutput')->returns($migrator);
        $migrator->expects('run')->with([__DIR__.DIRECTORY_SEPARATOR.'migrations'], ['pretend' => false, 'step' => true]);
        $migrator->expects('repositoryExists')->returns(true);

        $this->runCommand($command, ['--step' => true]);
    }

    protected function runCommand($command, $input = [])
    {
        return $command->run(new ArrayInput($input), new NullOutput);
    }
}

class ApplicationDatabaseMigrationStub extends Application
{
    public function __construct(array $data = [])
    {
        $mutex = TestDouble::for(CommandMutex::class);
        $mutex->allows('create')->returns(true);
        $mutex->allows('release')->returns(true);
        $this->instance(CommandMutex::class, $mutex);

        foreach ($data as $abstract => $instance) {
            $this->instance($abstract, $instance);
        }
    }

    public function environment(...$environments)
    {
        return 'development';
    }
}
