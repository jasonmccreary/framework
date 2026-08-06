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
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $builder = new PostgresBuilder($connection);
        $grammar->expects('compileTableExists')->times(2)->returns('sql');
        $connection->expects('getTablePrefix')->times(2)->returns('prefix_');
        $connection->expects('scalar')->times(2)->with('sql')->returns(1);

        $this->assertTrue($builder->hasTable('table'));
        $this->assertTrue($builder->hasTable('public.table'));
    }

    public function testGetColumnListing()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(PostgresGrammar::class);
        $processor = TestDouble::for(PostgresProcessor::class);
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $connection->allows('getPostProcessor')->returns($processor);
        $grammar->expects('compileColumns')->with(null, 'prefix_table')->returns('sql');
        $processor->expects('processColumns')->returns([['name' => 'column']]);
        $builder = new PostgresBuilder($connection);
        $connection->expects('getTablePrefix')->returns('prefix_');
        $connection->expects('selectFromWriteConnection')->with('sql')->returns([['name' => 'column']]);

        $this->assertEquals(['column'], $builder->getColumnListing('table'));
    }
}
