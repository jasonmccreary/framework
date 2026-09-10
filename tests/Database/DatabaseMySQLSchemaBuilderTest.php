<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;

class DatabaseMySQLSchemaBuilderTest extends TestCase
{
    public function testHasTable()
    {
        $connection = Double::for(Connection::class);
        $grammar = Double::for(MySqlGrammar::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $builder = new MySqlBuilder($connection);
        $grammar->expects('compileTableExists')->returns('sql');
        $connection->expects('getTablePrefix')->returns('prefix_');
        $connection->expects('scalar')->with('sql')->returns(1);

        $this->assertTrue($builder->hasTable('table'));
    }

    public function testGetColumnListing()
    {
        $connection = Double::for(Connection::class);
        $grammar = Double::for(MySqlGrammar::class);
        $processor = Double::for(MySqlProcessor::class);
        $connection->expects('getSchemaGrammar')->returns($grammar);
        $connection->expects('getPostProcessor')->returns($processor);
        $grammar->expects('compileColumns')->with(null, 'prefix_table')->returns('sql');
        $processor->expects('processColumns')->returns([['name' => 'column']]);
        $builder = new MySqlBuilder($connection);
        $connection->expects('getTablePrefix')->returns('prefix_');
        $connection->expects('selectFromWriteConnection')->with('sql')->returns([['name' => 'column']]);

        $this->assertEquals(['column'], $builder->getColumnListing('table'));
    }
}
