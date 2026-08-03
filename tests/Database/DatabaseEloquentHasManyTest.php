<?php

namespace Illuminate\Tests\Database;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;
use stdClass;

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
        $relation->getQuery()->expects('find')->with('foo', ['*'])->returns($model = TestDouble::for(stdClass::class));
        $model->expects('setAttribute')->never();

        $this->assertInstanceOf(stdClass::class, $relation->findOrNew('foo'));
    }

    public function testFindOrNewMethodReturnsNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('find')->with('foo', ['*'])->returns(null);
        $relation->getRelated()->expects('newInstance')->with()->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('foreign_key', 1);

        $this->assertInstanceOf(Model::class, $relation->findOrNew('foo'));
    }

    public function testFirstOrNewMethodFindsFirstModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(stdClass::class));
        $model->expects('setAttribute')->never();

        $this->assertInstanceOf(stdClass::class, $relation->firstOrNew(['foo']));
    }

    public function testFirstOrNewMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(stdClass::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();

        $this->assertInstanceOf(stdClass::class, $relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']));
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
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(stdClass::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();
        $model->expects('save')->never();

        $this->assertInstanceOf(stdClass::class, $relation->firstOrCreate(['foo']));
    }

    public function testFirstOrCreateMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(stdClass::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();
        $model->expects('save')->never();

        $this->assertInstanceOf(stdClass::class, $relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testFirstOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(fn ($scope) => $scope());
        $model = $this->expectCreatedModel($relation, ['foo']);

        $this->assertEquals($model, $relation->firstOrCreate(['foo']));
    }

    public function testFirstOrCreateMethodWithValuesCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(fn ($scope) => $scope());
        $model = $this->expectCreatedModel($relation, ['foo' => 'bar', 'baz' => 'qux']);

        $this->assertEquals($model, $relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testCreateOrFirstMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getRelation();

        $relation->getRelated()->expects('newInstance')->with(['foo' => 'bar', 'baz' => 'qux'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('foreign_key', 1);
        $model->expects('save')->throws(new UniqueConstraintViolationException('mysql', 'example mysql', [], new Exception('SQLSTATE[23000]: Integrity constraint violation: 1062')));

        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('useWritePdo')->returns($relation->getQuery());
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(stdClass::class));

        $this->assertInstanceOf(stdClass::class, $found = $relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']));
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
        $relation = $this->getRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(stdClass::class));
        $relation->getRelated()->expects('newInstance')->never();

        $model->wasRecentlyCreated = false;
        $model->expects('fill')->with(['bar'])->returns($model);
        $model->expects('save');

        $this->assertInstanceOf(stdClass::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testUpdateOrCreateMethodCreatesNewModelWithForeignKeySet()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getRelated()->expects('newInstance')->with(['foo', 'bar'])->returns($model = TestDouble::for(Model::class));

        $model->wasRecentlyCreated = true;
        $model->expects('save')->returns(true);
        $model->expects('setAttribute')->with('foreign_key', 1);

        $this->assertInstanceOf(Model::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testRelationUpsertFillsForeignKey()
    {
        $relation = $this->getRelation();

        $relation->getQuery()->expects('upsert')->with([
            ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey()],
        ],
            ['email'],
            ['name']);

        $relation->upsert(
            ['email' => 'foo3', 'name' => 'bar'],
            ['email'],
            ['name']
        );

        $relation->getQuery()->expects('upsert')->with([
            ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey()],
            ['name' => 'bar2', 'email' => 'foo2', $relation->getForeignKeyName() => $relation->getParentKey()],
        ],
            ['email'],
            ['name']);

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
        $model = TestDouble::for(Model::class);
        $relation->getRelated()->allows('newCollection')->resolves(function ($array = []) {
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
        $relation->getQuery()->expects('whereIntegerInRaw')->with('table.foreign_key', [1, 2]);
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
        $relation->getQuery()->expects('whereIn')->with('table.foreign_key', [1, 2]);
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

        $relation->getRelated()->allows('newCollection')->resolves(function ($array) {
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
        $queryBuilder = TestDouble::for(QueryBuilder::class);
        $builder = TestDouble::for(new Builder($queryBuilder));
        $builder->allows('whereNotNull')->with('table.foreign_key');
        $builder->allows('where')->with('table.foreign_key', '=', 1);
        $related = TestDouble::for(Model::class);
        $builder->allows('getModel')->returns($related);
        $parent = TestDouble::for(Model::class);
        $parent->allows('getAttribute')->with('id')->returns(1);
        $parent->allows('getCreatedAtColumn')->returns('created_at');
        $parent->allows('getUpdatedAtColumn')->returns('updated_at');

        return new HasMany($builder, $parent, 'table.foreign_key', 'id');
    }

    protected function expectNewModel($relation, $attributes = null)
    {
        $model = $this->getMockBuilder(Model::class)->onlyMethods(['setAttribute', 'save'])->getMock();
        $relation->getRelated()->allows('newInstance')->with($attributes)->returns($model);
        $model->expects($this->once())->method('setAttribute')->with('foreign_key', 1);

        return $model;
    }

    protected function expectCreatedModel($relation, $attributes)
    {
        $model = $this->expectNewModel($relation, $attributes);
        $model->expects($this->once())->method('save');

        return $model;
    }

    protected function expectForceCreatedModel($relation, $attributes)
    {
        $attributes[$relation->getForeignKeyName()] = $relation->getParentKey();

        $model = TestDouble::for(Model::class);
        $model->allows('getAttribute')->with($relation->getForeignKeyName())->returns($relation->getParentKey());

        $relation->getRelated()->expects('forceCreate')->with($attributes)->returns($model);

        return $model;
    }
}

class EloquentHasManyModelStub extends Model
{
    public $foreign_key = 'foreign.value';
}
