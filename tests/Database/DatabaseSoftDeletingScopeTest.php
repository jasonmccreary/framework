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
        $model->shouldReceive('getQualifiedDeletedAtColumn')->once()->andReturn('table.deleted_at');
        $builder->shouldReceive('whereNull')->once()->with('table.deleted_at');

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
        $givenBuilder->shouldReceive('withTrashed')->once();
        $givenBuilder->shouldReceive('getModel')->once()->andReturn($model = TestDouble::for(stdClass::class));
        $model->shouldReceive('getDeletedAtColumn')->once()->andReturn('deleted_at');
        $givenBuilder->shouldReceive('update')->once()->with(['deleted_at' => null]);

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
        $givenBuilder->shouldReceive('withTrashed')->once();
        $attributes = ['name' => 'foo'];
        $values = ['email' => 'bar'];
        $givenBuilder->shouldReceive('firstOrCreate')->once()->with($attributes, $values)->andReturn($model = TestDouble::for(Model::class));
        $model->shouldReceive('restore')->once()->andReturn(true);
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
        $givenBuilder->shouldReceive('withTrashed')->once();
        $attributes = ['name' => 'foo'];
        $values = ['email' => 'bar'];
        $givenBuilder->shouldReceive('createOrFirst')->once()->with($attributes, $values)->andReturn($model = TestDouble::for(Model::class));
        $model->shouldReceive('restore')->once()->andReturn(true);
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
        $givenBuilder->shouldReceive('getModel')->andReturn($model = TestDouble::for(Model::class));
        $givenBuilder->shouldReceive('withoutGlobalScope')->with($scope)->andReturn($givenBuilder);
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
        $model->makePartial();
        $scope = TestDouble::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('onlyTrashed');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->shouldReceive('getQuery')->andReturn($query = TestDouble::for(stdClass::class));
        $givenBuilder->shouldReceive('getModel')->andReturn($model);
        $givenBuilder->shouldReceive('withoutGlobalScope')->with($scope)->andReturn($givenBuilder);
        $model->shouldReceive('getQualifiedDeletedAtColumn')->andReturn('table.deleted_at');
        $givenBuilder->shouldReceive('whereNotNull')->once()->with('table.deleted_at');
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
        $model->makePartial();
        $scope = TestDouble::for(SoftDeletingScope::class)->passthru();
        $scope->extend($builder);
        $callback = $builder->getMacro('withoutTrashed');
        $givenBuilder = TestDouble::for(EloquentBuilder::class);
        $givenBuilder->shouldReceive('getQuery')->andReturn($query = TestDouble::for(stdClass::class));
        $givenBuilder->shouldReceive('getModel')->andReturn($model);
        $givenBuilder->shouldReceive('withoutGlobalScope')->with($scope)->andReturn($givenBuilder);
        $model->shouldReceive('getQualifiedDeletedAtColumn')->andReturn('table.deleted_at');
        $givenBuilder->shouldReceive('whereNull')->once()->with('table.deleted_at');
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }
}
