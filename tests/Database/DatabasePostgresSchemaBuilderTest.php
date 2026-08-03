<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Database\Schema\PostgresBuilder;
use PHPUnit\Framework\TestCase;

class DatabasePostgresSchemaBuilderTest extends TestCase
{
    public function testHasTable()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $builder = new PostgresBuilder($connection);
        $grammar->shouldReceive('compileTableExists')->twice()->andReturn('sql');
        $connection->shouldReceive('getTablePrefix')->twice()->andReturn('prefix_');
        $connection->shouldReceive('scalar')->twice()->with('sql')->andReturn(1);

        $this->assertTrue($builder->hasTable('table'));
        $this->assertTrue($builder->hasTable('public.table'));
    }

    public function testGetColumnListing()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $grammar->shouldReceive('compileColumns')->with(null, 'prefix_table')->once()->andReturn('sql');
        $processor->shouldReceive('processColumns')->once()->andReturn([['name' => 'column']]);
        $builder = new PostgresBuilder($connection);
        $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
        $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'column']]);

        $this->assertEquals(['column'], $builder->getColumnListing('table'));
    }
}
