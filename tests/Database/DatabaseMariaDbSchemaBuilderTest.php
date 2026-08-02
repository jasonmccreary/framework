<?php

namespace Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\MariaDbProcessor;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar;
use Illuminate\Database\Schema\MariaDbBuilder;
use PHPUnit\Framework\TestCase;

class DatabaseMariaDbSchemaBuilderTest extends TestCase
{
    public function testHasTable()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(MariaDbGrammar::class);
        $connection->allows('getDatabaseName')->returns('db');
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $builder = new MariaDbBuilder($connection);
        $grammar->expects('compileTableExists')->returns('sql');
        $connection->expects('getTablePrefix')->returns('prefix_');
        $connection->expects('scalar')->with('sql')->returns(1);

        $this->assertTrue($builder->hasTable('table'));
    }

    public function testGetColumnListing()
    {
        $connection = TestDouble::for(Connection::class);
        $grammar = TestDouble::for(MariaDbGrammar::class);
        $processor = TestDouble::for(MariaDbProcessor::class);
        $connection->allows('getDatabaseName')->returns('db');
        $connection->allows('getSchemaGrammar')->returns($grammar);
        $connection->allows('getPostProcessor')->returns($processor);
        $grammar->expects('compileColumns')->with(null, 'prefix_table')->returns('sql');
        $processor->expects('processColumns')->returns([['name' => 'column']]);
        $builder = new MariaDbBuilder($connection);
        $connection->expects('getTablePrefix')->returns('prefix_');
        $connection->expects('selectFromWriteConnection')->with('sql')->returns([['name' => 'column']]);

        $this->assertEquals(['column'], $builder->getColumnListing('table'));
    }
}
