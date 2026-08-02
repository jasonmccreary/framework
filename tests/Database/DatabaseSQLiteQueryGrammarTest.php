<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use PHPUnit\Framework\TestCase;

class DatabaseSQLiteQueryGrammarTest extends TestCase
{
    public function testToRawSql()
    {
        $connection = TestDouble::for(Connection::class);
        $connection->allows('escape')->with('foo', false)->returns("'foo'");
        $grammar = new SQLiteGrammar($connection);

        $query = $grammar->substituteBindingsIntoRawSql(
            'select * from "users" where \'Hello\'\'World?\' IS NOT NULL AND "email" = ?',
            ['foo'],
        );

        $this->assertSame('select * from "users" where \'Hello\'\'World?\' IS NOT NULL AND "email" = \'foo\'', $query);
    }
}
