<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Schema\SqliteSchemaState;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Filesystem\Filesystem;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DatabaseSqliteSchemaStateTest extends TestCase
{
    public function testLoadSchemaToDatabase(): void
    {
        $config = ['driver' => 'sqlite', 'database' => 'database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => true, 'name' => 'sqlite'];
        $connection = TestDouble::for(SQLiteConnection::class);
        $connection->allows('getConfig')->returns($config);
        $connection->allows('getDatabaseName')->returns($config['database']);

        $process = TestDouble::for(Process::class);
        $processFactory = m::spy(function () use ($process) {
            return $process;
        });

        $schemaState = new SqliteSchemaState($connection, null, $processFactory);
        $schemaState->load('database/schema/sqlite-schema.dump');


        $process->received('mustRun')->with(null, [
            'LARAVEL_LOAD_DATABASE' => 'database/database.sqlite',
            'LARAVEL_LOAD_PATH' => 'database/schema/sqlite-schema.dump',
        ]);
    }

    public function testLoadSchemaToInMemory(): void
    {
        $config = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true, 'name' => 'sqlite'];
        $connection = TestDouble::for(SQLiteConnection::class);
        $connection->allows('getConfig')->returns($config);
        $connection->allows('getDatabaseName')->returns($config['database']);
        $connection->allows('getPdo')->returns($pdo = TestDouble::for(PDO::class));

        $files = TestDouble::for(Filesystem::class);
        $files->allows('get')->returns('CREATE TABLE IF NOT EXISTS "migrations" ("id" integer not null primary key autoincrement, "migration" varchar not null, "batch" integer not null);');

        $schemaState = new SqliteSchemaState($connection, $files);
        $schemaState->load('database/schema/sqlite-schema.dump');

        $pdo->received('exec')->with('CREATE TABLE IF NOT EXISTS "migrations" ("id" integer not null primary key autoincrement, "migration" varchar not null, "batch" integer not null);');
    }
}
