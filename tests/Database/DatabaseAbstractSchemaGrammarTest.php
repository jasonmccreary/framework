<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;

class DatabaseAbstractSchemaGrammarTest extends TestCase
{
    public function testCreateDatabase()
    {
        $connection = Double::for(Connection::class);
        $grammar = new class($connection) extends Grammar {
        };

        $this->assertSame('create database "foo"', $grammar->compileCreateDatabase('foo'));
    }

    public function testDropDatabaseIfExists()
    {
        $connection = Double::for(Connection::class);
        $grammar = new class($connection) extends Grammar {
        };

        $this->assertSame('drop database if exists "foo"', $grammar->compileDropDatabaseIfExists('foo'));
    }
}
