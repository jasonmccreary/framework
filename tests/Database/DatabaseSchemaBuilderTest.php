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
        $grammar->allows('compileCreateDatabase')->returns('sql');
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $connection->allows('statement')->with('sql')->returns(true);
        $builder = new Builder($connection);

        $this->assertTrue($builder->createDatabase('foo'));
    }

    public function testDropDatabaseIfExists()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(stdClass::class);
        $grammar->allows('compileDropDatabaseIfExists')->returns('sql');
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $connection->allows('statement')->with('sql')->returns(true);
        $builder = new Builder($connection);

        $this->assertTrue($builder->dropDatabaseIfExists('foo'));
    }

    public function testHasTableCorrectlyCallsGrammar()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(Grammar::class);
        $processor = TestDouble::for(Processor::class);
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $connection->allows('getPostProcessor')->returns($processor);
        $builder = new Builder($connection);
        $grammar->expects('compileTableExists');
        $grammar->expects('compileTables')->returns('sql');
        $processor->expects('processTables')->returns([['name' => 'prefix_table']]);
        $connection->expects('getTablePrefix')->returns('prefix_');
        $connection->expects('selectFromWriteConnection')->with('sql')->returns([['name' => 'prefix_table']]);

        $this->assertTrue($builder->hasTable('table'));
    }

    public function testTableHasColumns()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(stdClass::class);
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $builder = m::mock(Builder::class.'[getColumnListing]', [$connection]);
        $builder->shouldReceive('getColumnListing')->with('users')->twice()->andReturn(['id', 'firstname']);

        $this->assertTrue($builder->hasColumns('users', ['id', 'firstname']));
        $this->assertFalse($builder->hasColumns('users', ['id', 'address']));
    }

    public function testGetColumnTypeAddsPrefix()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(Grammar::class);
        $processor = TestDouble::for(Processor::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $connection->allows('getPostProcessor')->returns($processor);
        $processor->expects('processColumns')->returns([['name' => 'id', 'type_name' => 'integer']]);
        $builder = new Builder($connection);
        $connection->expects('getTablePrefix')->returns('prefix_');
        $grammar->expects('compileColumns')->with(null, 'prefix_users')->returns('sql');
        $connection->expects('selectFromWriteConnection')->with('sql')->returns([['name' => 'id', 'type_name' => 'integer']]);

        $this->assertSame('integer', $builder->getColumnType('users', 'id'));
    }
}
