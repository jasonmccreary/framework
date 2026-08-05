<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use PHPUnit\Framework\TestCase;

class DatabaseSqlServerQueryGrammarTest extends TestCase
{
    public function testToRawSql()
    {
        $connection = TestDouble::for(Connection::class);
        $connection->allows('escape')->with('foo', false)->returns("'foo'");
        $grammar = new SqlServerGrammar($connection);

        $query = $grammar->substituteBindingsIntoRawSql(
            "select * from [users] where 'Hello''World?' IS NOT NULL AND [email] = ?",
            ['foo'],
        );

        $this->assertSame("select * from [users] where 'Hello''World?' IS NOT NULL AND [email] = 'foo'", $query);
    }
}
