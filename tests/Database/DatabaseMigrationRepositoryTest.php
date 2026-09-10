<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Double;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class DatabaseMigrationRepositoryTest extends TestCase
{
    public function testGetRanMigrationsListMigrationsByPackage()
    {
        $repo = $this->getRepository();
        $query = Double::for(QueryBuilder::class);
        $connectionMock = Double::for(Connection::class);
        $repo->getConnectionResolver()->expects('connection')->times(2)->with(null)->returns($connectionMock);
        $repo->getConnection()->expects('table')->with('migrations')->returns($query);
        $query->expects('orderBy')->with('batch', 'asc')->returns($query);
        $query->expects('orderBy')->with('migration', 'asc')->returns($query);
        $query->expects('pluck')->with('migration')->returns(new Collection(['bar']));
        $query->expects('useWritePdo')->returns($query);

        $this->assertEquals(['bar'], $repo->getRan());
    }

    public function testGetLastMigrationsGetsAllMigrationsWithTheLatestBatchNumber()
    {
        $resolver = Double::for(ConnectionResolverInterface::class);
        $repo = $this->getMockBuilder(DatabaseMigrationRepository::class)->onlyMethods(['getLastBatchNumber'])->setConstructorArgs([
            $resolver, 'migrations',
        ])->getMock();
        $repo->expects($this->once())->method('getLastBatchNumber')->willReturn(1);
        $query = Double::for(QueryBuilder::class);
        $connectionMock = Double::for(Connection::class);
        $repo->getConnectionResolver()->expects('connection')->times(2)->with(null)->returns($connectionMock);
        $repo->getConnection()->expects('table')->with('migrations')->returns($query);
        $query->expects('where')->with('batch', 1)->returns($query);
        $query->expects('orderBy')->with('migration', 'desc')->returns($query);
        $query->expects('get')->returns(new Collection(['foo']));
        $query->expects('useWritePdo')->returns($query);

        $this->assertEquals(['foo'], $repo->getLast());
    }

    public function testLogMethodInsertsRecordIntoMigrationTable()
    {
        $repo = $this->getRepository();
        $query = Double::for(QueryBuilder::class);
        $connectionMock = Double::for(Connection::class);
        $repo->getConnectionResolver()->expects('connection')->times(2)->with(null)->returns($connectionMock);
        $repo->getConnection()->expects('table')->with('migrations')->returns($query);
        $query->expects('insert')->with(['migration' => 'bar', 'batch' => 1]);
        $query->expects('useWritePdo')->returns($query);

        $repo->log('bar', 1);
    }

    public function testDeleteMethodRemovesAMigrationFromTheTable()
    {
        $repo = $this->getRepository();
        $query = Double::for(QueryBuilder::class);
        $connectionMock = Double::for(Connection::class);
        $repo->getConnectionResolver()->expects('connection')->times(2)->with(null)->returns($connectionMock);
        $repo->getConnection()->expects('table')->with('migrations')->returns($query);
        $query->expects('where')->with('migration', 'foo')->returns($query);
        $query->expects('delete');
        $query->expects('useWritePdo')->returns($query);
        $migration = (object) ['migration' => 'foo'];

        $repo->delete($migration);
    }

    public function testGetNextBatchNumberReturnsLastBatchNumberPlusOne()
    {
        $repo = $this->getMockBuilder(DatabaseMigrationRepository::class)->onlyMethods(['getLastBatchNumber'])->setConstructorArgs([
            Double::for(ConnectionResolverInterface::class), 'migrations',
        ])->getMock();
        $repo->expects($this->once())->method('getLastBatchNumber')->willReturn(1);

        $this->assertEquals(2, $repo->getNextBatchNumber());
    }

    public function testGetLastBatchNumberReturnsMaxBatch()
    {
        $repo = $this->getRepository();
        $query = Double::for(QueryBuilder::class);
        $connectionMock = Double::for(Connection::class);
        $repo->getConnectionResolver()->expects('connection')->times(2)->with(null)->returns($connectionMock);
        $repo->getConnection()->expects('table')->with('migrations')->returns($query);
        $query->expects('max')->returns(1);
        $query->expects('useWritePdo')->returns($query);

        $this->assertEquals(1, $repo->getLastBatchNumber());
    }

    public function testCreateRepositoryCreatesProperDatabaseTable()
    {
        $repo = $this->getRepository();
        $schema = Double::for(SchemaBuilder::class);
        $connectionMock = Double::for(Connection::class);
        $repo->getConnectionResolver()->expects('connection')->times(2)->with(null)->returns($connectionMock);
        $repo->getConnection()->expects('getSchemaBuilder')->returns($schema);
        $schema->expects('create')->with('migrations', Argument::type(Closure::class));

        $repo->createRepository();
    }

    protected function getRepository()
    {
        return new DatabaseMigrationRepository(Double::for(ConnectionResolverInterface::class), 'migrations');
    }
}
