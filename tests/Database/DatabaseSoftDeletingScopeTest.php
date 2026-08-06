<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use PHPUnit\Framework\TestCase;
use stdClass;

class DatabaseSoftDeletingScopeTest extends TestCase
{
    public function testApplyingScopeToABuilder()
    {
        $scope = TestDouble::for(SoftDeletingScope::class)->passthru();
        $builder = TestDouble::for(EloquentBuilder::class);
        $model = TestDouble::for(Model::class);
        $model->expects('getQualifiedDeletedAtColumn')->returns('table.deleted_at');
        $builder->expects('whereNull')->with('table.deleted_at');

        $scope->apply($builder, $model);
    }

    public function testRestoreExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            TestDouble::for(ConnectionInterface::class),
            TestDouble::for(Grammar::class),
            TestDouble::for(Processor::class)
        ));
        $scope = new SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('restore');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->expects('withTrashed');
        $givenBuilder->expects('getModel')->returns($model = TestDouble::for(stdClass::class));
        $model->expects('getDeletedAtColumn')->returns('deleted_at');
        $givenBuilder->expects('update')->with(['deleted_at' => null]);

        $callback($givenBuilder);
    }

    public function testRestoreOrCreateExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            TestDouble::for(ConnectionInterface::class),
            TestDouble::for(Grammar::class),
            TestDouble::for(Processor::class)
        ));

        $scope = new SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('restoreOrCreate');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->expects('withTrashed');
        $attributes = ['name' => 'foo'];
        $values = ['email' => 'bar'];
        $givenBuilder->expects('firstOrCreate')->with($attributes, $values)->returns($model = TestDouble::for(Model::class));
        $model->expects('restore')->returns(true);
        $result = $callback($givenBuilder, $attributes, $values);

        $this->assertEquals($model, $result);
    }

    public function testCreateOrRestoreExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            TestDouble::for(ConnectionInterface::class),
            TestDouble::for(Grammar::class),
            TestDouble::for(Processor::class)
        ));

        $scope = new SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('createOrRestore');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->expects('withTrashed');
        $attributes = ['name' => 'foo'];
        $values = ['email' => 'bar'];
        $givenBuilder->expects('createOrFirst')->with($attributes, $values)->returns($model = TestDouble::for(Model::class));
        $model->expects('restore')->returns(true);
        $result = $callback($givenBuilder, $attributes, $values);

        $this->assertEquals($model, $result);
    }

    public function testWithTrashedExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            TestDouble::for(ConnectionInterface::class),
            TestDouble::for(Grammar::class),
            TestDouble::for(Processor::class)
        ));
        $scope = TestDouble::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('withTrashed');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->allows('getModel')->returns($model = TestDouble::for(Model::class));
        $givenBuilder->allows('withoutGlobalScope')->with($scope)->returns($givenBuilder);
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }

    public function testOnlyTrashedExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            TestDouble::for(ConnectionInterface::class),
            TestDouble::for(Grammar::class),
            TestDouble::for(Processor::class)
        ));
        $model = TestDouble::for(Model::class);
        $model->passthru();
        $scope = TestDouble::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('onlyTrashed');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->allows('getQuery')->returns($query = TestDouble::for(stdClass::class));
        $givenBuilder->allows('getModel')->returns($model);
        $givenBuilder->allows('withoutGlobalScope')->with($scope)->returns($givenBuilder);
        $model->allows('getQualifiedDeletedAtColumn')->returns('table.deleted_at');
        $givenBuilder->expects('whereNotNull')->with('table.deleted_at');
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }

    public function testWithoutTrashedExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            TestDouble::for(ConnectionInterface::class),
            TestDouble::for(Grammar::class),
            TestDouble::for(Processor::class)
        ));
        $model = TestDouble::for(Model::class);
        $model->passthru();
        $scope = TestDouble::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('withoutTrashed');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->allows('getQuery')->returns($query = TestDouble::for(stdClass::class));
        $givenBuilder->allows('getModel')->returns($model);
        $givenBuilder->allows('withoutGlobalScope')->with($scope)->returns($givenBuilder);
        $model->allows('getQualifiedDeletedAtColumn')->returns('table.deleted_at');
        $givenBuilder->expects('whereNull')->with('table.deleted_at');
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }
}
