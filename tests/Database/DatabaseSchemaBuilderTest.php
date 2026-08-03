<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Grammars\Grammar;
use PHPUnit\Framework\TestCase;
use stdClass;

class DatabaseSchemaBuilderTest extends TestCase
{
    public function testCreateDatabase()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(stdClass::class);
        $grammar->shouldReceive('compileCreateDatabase')->andReturn('sql');
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('statement')->with('sql')->andReturnTrue();
        $builder = new Builder($connection);

        $this->assertTrue($builder->createDatabase('foo'));
    }

    public function testDropDatabaseIfExists()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(stdClass::class);
        $grammar->shouldReceive('compileDropDatabaseIfExists')->andReturn('sql');
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('statement')->with('sql')->andReturnTrue();
        $builder = new Builder($connection);

        $this->assertTrue($builder->dropDatabaseIfExists('foo'));
    }

    public function testHasTableCorrectlyCallsGrammar()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(Grammar::class);
        $processor = TestDouble::for(Processor::class);
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $builder = new Builder($connection);
        $grammar->shouldReceive('compileTableExists');
        $grammar->shouldReceive('compileTables')->once()->andReturn('sql');
        $processor->shouldReceive('processTables')->once()->andReturn([['name' => 'prefix_table']]);
        $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
        $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'prefix_table']]);

        $this->assertTrue($builder->hasTable('table'));
    }

    public function testTableHasColumns()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(stdClass::class);
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $builder = TestDouble::for(Builder::class)->passthru(new Builder($connection));
        $builder->shouldReceive('getColumnListing')->with('users')->twice()->andReturn(['id', 'firstname']);

        $this->assertTrue($builder->hasColumns('users', ['id', 'firstname']));
        $this->assertFalse($builder->hasColumns('users', ['id', 'address']));
    }

    public function testGetColumnTypeAddsPrefix()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(Grammar::class);
        $processor = TestDouble::for(Processor::class);
        $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $processor->shouldReceive('processColumns')->once()->andReturn([['name' => 'id', 'type_name' => 'integer']]);
        $builder = new Builder($connection);
        $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
        $grammar->shouldReceive('compileColumns')->once()->with(null, 'prefix_users')->andReturn('sql');
        $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'id', 'type_name' => 'integer']]);

        $this->assertSame('integer', $builder->getColumnType('users', 'id'));
    }
}
