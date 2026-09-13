<?php

namespace Illuminate\Tests\Database;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Mockery;
use PHPUnit\Framework\TestCase;

class DatabaseSQLiteBuilderTest extends TestCase
{
    use VerifiesDoubles;

    protected function setUp(): void
    {
        $app = new Container;

        Container::setInstance($app)
            ->singleton('files', Filesystem::class);

        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::setFacadeApplication(null);
    }

    public function testCreateDatabase()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->expects('getSchemaGrammar');

        $builder = new SQLiteBuilder($connection);

        File::expects('put')
            ->with('my_temporary_database_a', '')
            ->returns(20); // bytes

        $this->assertTrue($builder->createDatabase('my_temporary_database_a'));

        File::expects('put')
            ->with('my_temporary_database_b', '')
            ->returns(false);

        $this->assertFalse($builder->createDatabase('my_temporary_database_b'));
    }

    public function testDropDatabaseIfExists()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->expects('getSchemaGrammar');

        $builder = new SQLiteBuilder($connection);

        File::expects('exists')
            ->returns(true);

        File::expects('delete')
            ->with('my_temporary_database_b')
            ->returns(true);

        $this->assertTrue($builder->dropDatabaseIfExists('my_temporary_database_b'));

        File::expects('exists')
            ->returns(false);

        $this->assertTrue($builder->dropDatabaseIfExists('my_temporary_database_c'));

        File::expects('exists')
            ->returns(true);

        File::expects('delete')
            ->with('my_temporary_database_c')
            ->returns(false);

        $this->assertFalse($builder->dropDatabaseIfExists('my_temporary_database_c'));
    }
}
