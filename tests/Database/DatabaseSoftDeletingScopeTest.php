<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;

class DatabaseSoftDeletingScopeTest extends TestCase
{
    public function testApplyingScopeToABuilder()
    {
        $scope = Double::for(SoftDeletingScope::class)->passthru();
        $builder = Double::for(EloquentBuilder::class);
        $model = Double::for(Model::class);
        $model->expects('getQualifiedDeletedAtColumn')->returns('table.deleted_at');
        $builder->expects('qualifyColumn')->with('table.deleted_at')->returns('table.deleted_at');
        $builder->expects('whereNull')->with('table.deleted_at');

        $scope->apply($builder, $model);
    }

    public function testRestoreExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            Double::for(ConnectionInterface::class),
            Double::for(Grammar::class),
            Double::for(Processor::class)
        ));
        $scope = new SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('restore');
        $givenBuilder = Double::for(EloquentBuilder::class);
        $givenBuilder->expects('withTrashed');
        $model = Double::for(Model::class);
        $givenBuilder->expects('getModel')->returns($model);
        $model->expects('getDeletedAtColumn')->returns('deleted_at');
        $givenBuilder->expects('update')->with(['deleted_at' => null]);

        $callback($givenBuilder);
    }

    public function testRestoreOrCreateExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            Double::for(ConnectionInterface::class),
            Double::for(Grammar::class),
            Double::for(Processor::class)
        ));

        $scope = new SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('restoreOrCreate');
        $givenBuilder = Double::for(EloquentBuilder::class);
        $givenBuilder->expects('withTrashed');
        $attributes = ['name' => 'foo'];
        $values = ['email' => 'bar'];
        $model = Double::for(Model::class);
        $givenBuilder->expects('firstOrCreate')->with($attributes, $values)->returns($model);
        $model->expects('restore')->returns(true);
        $result = $callback($givenBuilder, $attributes, $values);

        $this->assertEquals($model, $result);
    }

    public function testCreateOrRestoreExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            Double::for(ConnectionInterface::class),
            Double::for(Grammar::class),
            Double::for(Processor::class)
        ));

        $scope = new SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('createOrRestore');
        $givenBuilder = Double::for(EloquentBuilder::class);
        $givenBuilder->expects('withTrashed');
        $attributes = ['name' => 'foo'];
        $values = ['email' => 'bar'];
        $model = Double::for(Model::class);
        $givenBuilder->expects('createOrFirst')->with($attributes, $values)->returns($model);
        $model->expects('restore')->returns(true);
        $result = $callback($givenBuilder, $attributes, $values);

        $this->assertEquals($model, $result);
    }

    public function testWithTrashedExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            Double::for(ConnectionInterface::class),
            Double::for(Grammar::class),
            Double::for(Processor::class)
        ));
        $scope = Double::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('withTrashed');
        $givenBuilder = Double::for(EloquentBuilder::class);
        $model = Double::for(Model::class);
        $givenBuilder->expects('withoutGlobalScope')->with($scope)->returns($givenBuilder);
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }

    public function testOnlyTrashedExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            Double::for(ConnectionInterface::class),
            Double::for(Grammar::class),
            Double::for(Processor::class)
        ));
        $model = Double::for(Model::class)->passthru();
        $scope = Double::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('onlyTrashed');
        $givenBuilder = Double::for(EloquentBuilder::class);
        $givenBuilder->expects('getModel')->returns($model);
        $givenBuilder->expects('withoutGlobalScope')->with($scope)->returns($givenBuilder);
        $model->expects('getQualifiedDeletedAtColumn')->returns('table.deleted_at');
        $givenBuilder->expects('qualifyColumn')->with('table.deleted_at')->returns('table.deleted_at');
        $givenBuilder->expects('whereNotNull')->with('table.deleted_at');
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }

    public function testWithoutTrashedExtension()
    {
        $builder = new EloquentBuilder(new BaseBuilder(
            Double::for(ConnectionInterface::class),
            Double::for(Grammar::class),
            Double::for(Processor::class)
        ));
        $model = Double::for(Model::class)->passthru();
        $scope = Double::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('withoutTrashed');
        $givenBuilder = Double::for(EloquentBuilder::class);
        $givenBuilder->expects('getModel')->returns($model);
        $givenBuilder->expects('withoutGlobalScope')->with($scope)->returns($givenBuilder);
        $model->expects('getQualifiedDeletedAtColumn')->returns('table.deleted_at');
        $givenBuilder->expects('qualifyColumn')->with('table.deleted_at')->returns('table.deleted_at');
        $givenBuilder->expects('whereNull')->with('table.deleted_at');
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }
}
