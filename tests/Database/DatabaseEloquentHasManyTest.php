<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Double;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Mockery;
use Illuminate\Tests\TestCase;

class DatabaseEloquentHasManyTest extends TestCase
{
    public function testMakeMethodDoesNotSaveNewModel()
    {
        $relation = $this->getRelation();
        $instance = $this->expectNewModel($relation, ['name' => 'taylor']);
        $instance->expects($this->never())->method('save');

        $this->assertEquals($instance, $relation->make(['name' => 'taylor']));
    }

    public function testMakeManyCreatesARelatedModelForEachRecord()
    {
        $records = [
            'taylor' => ['name' => 'taylor'],
            'colin' => ['name' => 'colin'],
        ];

        $relation = $this->getRelation();
        $relation->getRelated()->expects('newCollection')->returns(new Collection);

        $taylor = $this->expectNewModel($relation, ['name' => 'taylor']);
        $taylor->expects($this->never())->method('save');
        $colin = $this->expectNewModel($relation, ['name' => 'colin']);
        $colin->expects($this->never())->method('save');

        $instances = $relation->makeMany($records);
        $this->assertInstanceOf(Collection::class, $instances);
        $this->assertEquals($taylor, $instances[0]);
        $this->assertEquals($colin, $instances[1]);
    }

    public function testCreateMethodProperlyCreatesNewModel()
    {
        $relation = $this->getRelation();
        $created = $this->expectCreatedModel($relation, ['name' => 'taylor']);

        $this->assertEquals($created, $relation->create(['name' => 'taylor']));
    }

    public function testForceCreateMethodProperlyCreatesNewModel()
    {
        $relation = $this->getRelation();
        $created = $this->expectForceCreatedModel($relation, ['name' => 'taylor']);

        $this->assertEquals($created, $relation->forceCreate(['name' => 'taylor']));
        $this->assertEquals(1, $created->getAttribute('foreign_key'));
    }

    public function testFindOrNewMethodFindsModel()
    {
        $relation = $this->getRelation();
        $model = Double::for(Model::class);
        $relation->getQuery()->expects('find')->with('foo', ['*'])->returns($model);
        $model->expects('setAttribute')->never();

        $this->assertInstanceOf(Model::class, $relation->findOrNew('foo'));
    }

    public function testFindOrNewMethodReturnsNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('find')->with('foo', ['*'])->returns(null);
        $model = Double::for(Model::class);
        $relation->getRelated()->expects('newInstance')->with()->returns($model);
        $model->expects('setAttribute')->with('foreign_key', 1);

        $this->assertInstanceOf(Model::class, $relation->findOrNew('foo'));
    }

    public function testFirstOrNewMethodFindsFirstModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $model = Double::for(Model::class);
        $relation->getQuery()->expects('first')->with()->returns($model);
        $model->expects('setAttribute')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrNew(['foo']));
    }

    public function testFirstOrNewMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $model = Double::for(Model::class);
        $relation->getQuery()->expects('first')->with()->returns($model);
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testFirstOrNewMethodReturnsNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $model = $this->expectNewModel($relation, ['foo']);

        $this->assertEquals($model, $relation->firstOrNew(['foo']));
    }

    public function testFirstOrNewMethodWithValuesCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $model = $this->expectNewModel($relation, ['foo' => 'bar', 'baz' => 'qux']);

        $this->assertEquals($model, $relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testFirstOrCreateMethodFindsFirstModel()
    {
        $relation = $this->getMockeryRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->andReturn($relation->getQuery());
        $model = Mockery::mock(Model::class);
        $relation->getQuery()->expects('first')->with()->andReturn($model);
        $relation->getRelated()->shouldReceive('newInstance')->never();
        $model->shouldReceive('setAttribute')->never();
        $model->shouldReceive('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrCreate(['foo']));
    }

    public function testFirstOrCreateMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getMockeryRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->andReturn($relation->getQuery());
        $model = Mockery::mock(Model::class);
        $relation->getQuery()->expects('first')->with()->andReturn($model);
        $relation->getRelated()->shouldReceive('newInstance')->never();
        $model->shouldReceive('setAttribute')->never();
        $model->shouldReceive('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testFirstOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getMockeryRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->andReturn(null);
        $relation->getQuery()->expects('withSavepointIfNeeded')->andReturnUsing(fn ($scope) => $scope());
        $model = $this->expectCreatedModelMockery($relation, ['foo']);

        $this->assertEquals($model, $relation->firstOrCreate(['foo']));
    }

    public function testFirstOrCreateMethodWithValuesCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getMockeryRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->andReturn($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->andReturn(null);
        $relation->getQuery()->expects('withSavepointIfNeeded')->andReturnUsing(fn ($scope) => $scope());
        $model = $this->expectCreatedModelMockery($relation, ['foo' => 'bar', 'baz' => 'qux']);

        $this->assertEquals($model, $relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testCreateOrFirstMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getMockeryRelation();

        $relation->getRelated()->expects('newInstance')->with(['foo' => 'bar', 'baz' => 'qux'])->andReturn(Mockery::mock(Model::class, function ($model) {
            $model->expects('setAttribute')->with('foreign_key', 1);
            $model->expects('save')->andThrow(new UniqueConstraintViolationException('mysql', 'example mysql', [], new Exception('SQLSTATE[23000]: Integrity constraint violation: 1062')));
        }));

        $relation->getQuery()->expects('withSavepointIfNeeded')->andReturnUsing(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('useWritePdo')->andReturn($relation->getQuery());
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->andReturn($relation->getQuery());
        $model = Mockery::mock(Model::class);
        $relation->getQuery()->expects('first')->with()->andReturn($model);

        $this->assertInstanceOf(Model::class, $found = $relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']));
        $this->assertSame($model, $found);
    }

    public function testCreateOrFirstMethodCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();

        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('where')->never();
        $relation->getQuery()->expects('first')->never();
        $model = $this->expectCreatedModel($relation, ['foo']);

        $this->assertEquals($model, $relation->createOrFirst(['foo']));
    }

    public function testCreateOrFirstMethodWithValuesCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('where')->never();
        $relation->getQuery()->expects('first')->never();
        $model = $this->expectCreatedModel($relation, ['foo' => 'bar', 'baz' => 'qux']);

        $this->assertEquals($model, $relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testUpdateOrCreateMethodFindsFirstModelAndUpdates()
    {
        $relation = $this->getMockeryRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->andReturn($relation->getQuery());
        $model = Mockery::mock(Model::class);
        $relation->getQuery()->expects('first')->with()->andReturn($model);
        $relation->getRelated()->shouldReceive('newInstance')->never();

        $model->wasRecentlyCreated = false;
        $model->expects('fill')->with(['bar'])->andReturn($model);
        $model->expects('save');

        $this->assertInstanceOf(Model::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testUpdateOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getMockeryRelation();
        $relation->getQuery()->expects('withSavepointIfNeeded')->andReturnUsing(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('where')->with(['foo'])->andReturn($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->andReturn(null);
        $model = Mockery::mock(Model::class);
        $relation->getRelated()->expects('newInstance')->with(['foo', 'bar'])->andReturn($model);

        $model->wasRecentlyCreated = true;
        $model->expects('save')->andReturn(true);
        $model->expects('setAttribute')->with('foreign_key', 1);

        $this->assertInstanceOf(Model::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testRelationUpsertFillsForeignKey()
    {
        $relation = $this->getRelation();

        $relation->getQuery()->expects('upsert')->with(
            [
                ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey()],
            ],
            ['email'],
            ['name']
        );

        $relation->upsert(
            ['email' => 'foo3', 'name' => 'bar'],
            ['email'],
            ['name']
        );

        $relation->getQuery()->expects('upsert')->with(
            [
                ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey()],
                ['name' => 'bar2', 'email' => 'foo2', $relation->getForeignKeyName() => $relation->getParentKey()],
            ],
            ['email'],
            ['name']
        );

        $relation->upsert(
            [
                ['email' => 'foo3', 'name' => 'bar'],
                ['name' => 'bar2', 'email' => 'foo2'],
            ],
            ['email'],
            ['name']
        );
    }

    public function testRelationIsProperlyInitialized()
    {
        $relation = $this->getRelation();
        $model = Double::for(Model::class);
        $relation->getRelated()->expects('newCollection')->resolves(function ($array = []) {
            return new Collection($array);
        });
        $model->expects('setRelation')->with('foo', Argument::type(Collection::class));
        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getRelation();
        $relation->getParent()->expects('getKeyName')->returns('id');
        $relation->getParent()->expects('getKeyType')->returns('int');
        $relation->getQuery()->getQuery()->expects('whereIntegerInRaw')->with('table.foreign_key', [1, 2]);
        $model1 = new EloquentHasManyModelStub;
        $model1->id = 1;
        $model2 = new EloquentHasManyModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testEagerConstraintsAreProperlyAddedWithStringKey()
    {
        $relation = $this->getRelation();
        $relation->getParent()->expects('getKeyName')->returns('id');
        $relation->getParent()->expects('getKeyType')->returns('string');
        $relation->getQuery()->getQuery()->expects('whereIn')->with('table.foreign_key', [1, 2]);
        $model1 = new EloquentHasManyModelStub;
        $model1->id = 1;
        $model2 = new EloquentHasManyModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();

        $result1 = new EloquentHasManyModelStub;
        $result1->foreign_key = 1;
        $result2 = new EloquentHasManyModelStub;
        $result2->foreign_key = 2;
        $result3 = new EloquentHasManyModelStub;
        $result3->foreign_key = 2;

        $model1 = new EloquentHasManyModelStub;
        $model1->id = 1;
        $model2 = new EloquentHasManyModelStub;
        $model2->id = 2;
        $model3 = new EloquentHasManyModelStub;
        $model3->id = 3;

        $relation->getRelated()->expects('newCollection')->times(2)->resolves(function ($array) {
            return new Collection($array);
        });
        $models = $relation->match([$model1, $model2, $model3], new Collection([$result1, $result2, $result3]), 'foo');

        $this->assertEquals(1, $models[0]->foo[0]->foreign_key);
        $this->assertCount(1, $models[0]->foo);
        $this->assertEquals(2, $models[1]->foo[0]->foreign_key);
        $this->assertEquals(2, $models[1]->foo[1]->foreign_key);
        $this->assertCount(2, $models[1]->foo);
        $this->assertNull($models[2]->foo);
    }

    public function testCreateManyCreatesARelatedModelForEachRecord()
    {
        $records = [
            'taylor' => ['name' => 'taylor'],
            'colin' => ['name' => 'colin'],
        ];

        $relation = $this->getRelation();
        $relation->getRelated()->expects('newCollection')->returns(new Collection);

        $taylor = $this->expectCreatedModel($relation, ['name' => 'taylor']);
        $colin = $this->expectCreatedModel($relation, ['name' => 'colin']);

        $instances = $relation->createMany($records);
        $this->assertInstanceOf(Collection::class, $instances);
        $this->assertEquals($taylor, $instances[0]);
        $this->assertEquals($colin, $instances[1]);
    }

    protected function getRelation()
    {
        $queryBuilder = Double::for(QueryBuilder::class);
        $queryBuilder->allows('whereNotNull')->with('table.foreign_key');
        $builder = Double::for(new Builder($queryBuilder));
        $builder->allows('where')->with('table.foreign_key', '=', 1);
        $builder->allows('getQuery')->returns($queryBuilder);
        // Eloquent Builder's own __call() forwards undeclared methods (e.g.
        // whereIntegerInRaw/whereIn) to the underlying query builder via
        // forwardCallTo() — but forwardCallTo() is itself a proxied method on
        // this double, so it never runs for real and $this->query is never
        // populated (Double::for() doesn't run the real constructor). Stub it
        // to forward to $queryBuilder directly instead.
        $builder->allows('forwardCallTo')->resolves(
            fn ($object, $method, $parameters) => $queryBuilder->{$method}(...$parameters)
        );
        $related = Double::for(Model::class);
        $builder->allows('getModel')->returns($related);
        $parent = Double::for(Model::class);
        $parent->allows('getAttribute')->with('id')->returns(1);
        $parent->allows('getCreatedAtColumn')->returns('created_at');
        $parent->allows('getUpdatedAtColumn')->returns('updated_at');

        return new HasMany($builder, $parent, 'table.foreign_key', 'id');
    }

    protected function expectNewModel($relation, $attributes = null)
    {
        $model = $this->getMockBuilder(Model::class)->onlyMethods(['setAttribute', 'save'])->getMock();
        $relation->getRelated()->expects('newInstance')->with($attributes)->returns($model);
        $model->expects($this->once())->method('setAttribute')->with('foreign_key', 1);

        return $model;
    }

    protected function expectCreatedModel($relation, $attributes)
    {
        $model = $this->expectNewModel($relation, $attributes);
        $model->expects($this->once())->method('save');

        return $model;
    }

    /**
     * Mockery-backed counterpart to getRelation() — firstOrCreate()/createOrFirst()/
     * updateOrCreate() internally do `(clone $this)->where(...)`, and Double doesn't
     * support being cloned (its expectation state lives in an external identity-keyed
     * registry, so the clone comes back unregistered). Mockery mocks carry their
     * expectations as ordinary instance properties, so a clone works for free.
     */
    protected function getMockeryRelation()
    {
        $queryBuilder = Mockery::mock(QueryBuilder::class);
        $builder = Mockery::mock(Builder::class, [$queryBuilder]);
        $builder->shouldReceive('whereNotNull')->with('table.foreign_key');
        $builder->shouldReceive('where')->with('table.foreign_key', '=', 1);
        $related = Mockery::mock(Model::class);
        $builder->shouldReceive('getModel')->andReturn($related);
        $parent = Mockery::mock(Model::class);
        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
        $parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

        return new HasMany($builder, $parent, 'table.foreign_key', 'id');
    }

    protected function expectNewModelMockery($relation, $attributes = null)
    {
        $model = $this->getMockBuilder(Model::class)->onlyMethods(['setAttribute', 'save'])->getMock();
        $relation->getRelated()->expects('newInstance')->with($attributes)->andReturn($model);
        $model->expects($this->once())->method('setAttribute')->with('foreign_key', 1);

        return $model;
    }

    protected function expectCreatedModelMockery($relation, $attributes)
    {
        $model = $this->expectNewModelMockery($relation, $attributes);
        $model->expects($this->once())->method('save');

        return $model;
    }

    protected function expectForceCreatedModel($relation, $attributes)
    {
        $attributes[$relation->getForeignKeyName()] = $relation->getParentKey();

        $model = Double::for(Model::class);
        $model->expects('getAttribute')->with($relation->getForeignKeyName())->returns($relation->getParentKey());

        $query = Double::for(Builder::class);
        $query->expects('forceCreate')->with($attributes)->returns($model);
        $related = $relation->getRelated();
        $related->allows('newQuery')->returns($query);
        // Model::__call() forwards undeclared methods (like forceCreate) to
        // newQuery() via forwardCallTo() — but forwardCallTo() is itself a
        // proxied method on this double, so it never runs for real. Stub it
        // to forward to $query directly instead.
        $related->allows('forwardCallTo')->resolves(
            fn ($object, $method, $parameters) => $query->{$method}(...$parameters)
        );

        return $model;
    }
}

class EloquentHasManyModelStub extends Model
{
    public $foreign_key = 'foreign.value';
}
