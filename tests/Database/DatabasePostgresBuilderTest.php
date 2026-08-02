<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Database\Schema\PostgresBuilder;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;

class DatabasePostgresBuilderTest extends TestCase
{
    public function testCreateDatabase()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = new PostgresGrammar($connection);

        $connection->expects('getConfig')->with('charset')->returns('utf8');
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $connection->expects('statement')->with('create database "my_temporary_database" encoding "utf8"')->returns(true);

        $builder = $this->getBuilder($connection);
        $builder->createDatabase('my_temporary_database');
    }

    public function testDropDatabaseIfExists()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = new PostgresGrammar($connection);

        $connection->expects('getSchemaGrammar')->returns($grammar);
        $connection->expects('statement')->with('drop database if exists "my_database_a"')->returns(true);

        $builder = $this->getBuilder($connection);

        $builder->dropDatabaseIfExists('my_database_a');
    }

    public function testHasTableWhenSchemaUnqualifiedAndSearchPathMissing()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns(null);
        $connection->allows('getConfig')->with('schema')->returns(null);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileTableExists')->returns('sql');
        $connection->allows('scalar')->with('sql')->returns(1);
        $connection->allows('getTablePrefix');
        $builder = $this->getBuilder($connection);

        $this->assertTrue($builder->hasTable('foo'));
        $this->assertTrue($builder->hasTable('public.foo'));
    }

    public function testHasTableWhenSchemaUnqualifiedAndSearchPathFilled()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns('myapp,public');
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileTableExists')->returns('sql');
        $connection->allows('scalar')->with('sql')->returns(1);
        $connection->allows('getTablePrefix');
        $builder = $this->getBuilder($connection);

        $this->assertTrue($builder->hasTable('foo'));
        $this->assertTrue($builder->hasTable('myapp.foo'));
    }

    public function testHasTableWhenSchemaUnqualifiedAndSearchPathFallbackFilled()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns(null);
        $connection->allows('getConfig')->with('schema')->returns(['myapp', 'public']);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileTableExists')->returns('sql');
        $connection->allows('scalar')->with('sql')->returns(1);
        $connection->allows('getTablePrefix');
        $builder = $this->getBuilder($connection);

        $this->assertTrue($builder->hasTable('foo'));
        $this->assertTrue($builder->hasTable('myapp.foo'));
    }

    public function testHasTableWhenSchemaUnqualifiedAndSearchPathIsUserVariable()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('username')->returns('foouser');
        $connection->allows('getConfig')->with('search_path')->returns('$user');
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileTableExists')->returns('sql');
        $connection->allows('scalar')->with('sql')->returns(1);
        $connection->allows('getTablePrefix');
        $builder = $this->getBuilder($connection);

        $this->assertTrue($builder->hasTable('foo'));
        $this->assertTrue($builder->hasTable('foouser.foo'));
    }

    public function testHasTableWhenSchemaQualifiedAndSearchPathMismatches()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns('public');
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileTableExists')->returns('sql');
        $connection->allows('scalar')->with('sql')->returns(1);
        $connection->allows('getTablePrefix');
        $builder = $this->getBuilder($connection);

        $this->assertTrue($builder->hasTable('myapp.foo'));
    }

    public function testHasTableWhenDatabaseAndSchemaQualifiedAndSearchPathMismatches()
    {
        $this->expectException(\InvalidArgumentException::class);

        $connection = $this->getConnection();
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $builder = $this->getBuilder($connection);

        $builder->hasTable('mydatabase.myapp.foo');
    }

    public function testGetColumnListingWhenSchemaUnqualifiedAndSearchPathMissing()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns(null);
        $connection->allows('getConfig')->with('schema')->returns(null);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileColumns')->with(null, 'foo')->returns('sql');
        $connection->allows('selectFromWriteConnection')->with('sql')->returns([['name' => 'some_column']]);
        $connection->allows('getTablePrefix');
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->allows('getPostProcessor')->returns($processor);
        $processor->allows('processColumns')->returns([['name' => 'some_column']]);
        $builder = $this->getBuilder($connection);

        $builder->getColumnListing('foo');
    }

    public function testGetColumnListingWhenSchemaUnqualifiedAndSearchPathFilled()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns('myapp,public');
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileColumns')->with(null, 'foo')->returns('sql');
        $connection->allows('selectFromWriteConnection')->with('sql')->returns([['name' => 'some_column']]);
        $connection->allows('getTablePrefix');
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->allows('getPostProcessor')->returns($processor);
        $processor->allows('processColumns')->returns([['name' => 'some_column']]);
        $builder = $this->getBuilder($connection);

        $builder->getColumnListing('foo');
    }

    public function testGetColumnListingWhenSchemaUnqualifiedAndSearchPathIsUserVariable()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('username')->returns('foouser');
        $connection->allows('getConfig')->with('search_path')->returns('$user');
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileColumns')->with(null, 'foo')->returns('sql');
        $connection->allows('selectFromWriteConnection')->with('sql')->returns([['name' => 'some_column']]);
        $connection->allows('getTablePrefix');
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->allows('getPostProcessor')->returns($processor);
        $processor->allows('processColumns')->returns([['name' => 'some_column']]);
        $builder = $this->getBuilder($connection);

        $builder->getColumnListing('foo');
    }

    public function testGetColumnListingWhenSchemaQualifiedAndSearchPathMismatches()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns('public');
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $grammar->allows('compileColumns')->with('myapp', 'foo')->returns('sql');
        $connection->allows('selectFromWriteConnection')->with('sql')->returns([['name' => 'some_column']]);
        $connection->allows('getTablePrefix');
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->allows('getPostProcessor')->returns($processor);
        $processor->allows('processColumns')->returns([['name' => 'some_column']]);
        $builder = $this->getBuilder($connection);

        $builder->getColumnListing('myapp.foo');
    }

    public function testGetColumnWhenDatabaseAndSchemaQualifiedAndSearchPathMismatches()
    {
        $this->expectException(\InvalidArgumentException::class);

        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns('public');
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $builder = $this->getBuilder($connection);

        $builder->getColumnListing('mydatabase.myapp.foo');
    }

    public function testDropAllTablesWhenSearchPathIsString()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('search_path')->returns('public');
        $connection->allows('getConfig')->with('dont_drop')->returns(['foo']);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $connection->allows('getPostProcessor')->returns($processor);
        $grammar->allows('compileTables')->returns('sql');
        $processor->expects('processTables')->returns([['name' => 'users', 'schema' => 'public', 'schema_qualified_name' => 'public.users']]);
        $connection->allows('selectFromWriteConnection')->with('sql')->returns([['name' => 'users', 'schema' => 'public', 'schema_qualified_name' => 'public.users']]);
        $grammar->allows('compileDropAllTables')->with(['public.users'])->returns('drop table "public"."users" cascade');
        $connection->expects('statement')->with('drop table "public"."users" cascade');
        $builder = $this->getBuilder($connection);

        $builder->dropAllTables();
    }

    public function testDropAllTablesWhenSearchPathIsStringOfMany()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('username')->returns('foouser');
        $connection->allows('getConfig')->with('search_path')->returns('"$user", public, foo_bar-Baz.Áüõß');
        $connection->allows('getConfig')->with('dont_drop')->returns(['foo']);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $connection->allows('getPostProcessor')->returns($processor);
        $processor->expects('processTables')->returns([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
        $grammar->allows('compileTables')->returns('sql');
        $connection->allows('selectFromWriteConnection')->with('sql')->returns([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
        $grammar->allows('compileDropAllTables')->with(['foouser.users'])->returns('drop table "foouser"."users" cascade');
        $connection->expects('statement')->with('drop table "foouser"."users" cascade');
        $builder = $this->getBuilder($connection);

        $builder->dropAllTables();
    }

    public function testDropAllTablesWhenSearchPathIsArrayOfMany()
    {
        $connection = $this->getConnection();
        $connection->allows('getConfig')->with('username')->returns('foouser');
        $connection->allows('getConfig')->with('search_path')->returns([
            '$user',
            '"dev"',
            "'test'",
            'spaced schema',
        ]);
        $connection->allows('getConfig')->with('dont_drop')->returns(['foo']);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $connection->allows('getPostProcessor')->returns($processor);
        $processor->expects('processTables')->returns([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
        $grammar->allows('compileTables')->returns('sql');
        $connection->allows('selectFromWriteConnection')->with('sql')->returns([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
        $grammar->allows('compileDropAllTables')->with(['foouser.users'])->returns('drop table "foouser"."users" cascade');
        $connection->expects('statement')->with('drop table "foouser"."users" cascade');
        $builder = $this->getBuilder($connection);

        $builder->dropAllTables();
    }

    protected function getConnection()
    {
        return TestDouble::for(Connection::class);
    }

    protected function getBuilder($connection)
    {
        return new PostgresBuilder($connection);
    }

    protected function getGrammar()
    {
        return new PostgresGrammar;
    }
}
