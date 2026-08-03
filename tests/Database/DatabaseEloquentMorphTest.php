<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Exception;
use Foo\Bar\EloquentModelNamespacedStub;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;

class DatabaseEloquentMorphTest extends TestCase
{
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    public function testMorphOneSetsProperConstraints()
    {
        $this->getOneRelation();
    }

    public function testMorphOneEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getOneRelation();
        $relation->getParent()->expects('getKeyName')->returns('id');
        $relation->getParent()->expects('getKeyType')->returns('string');
        $relation->getQuery()->expects('whereIn')->with('table.morph_id', [1, 2]);
        $relation->getQuery()->expects('where')->with('table.morph_type', get_class($relation->getParent()));

        $model1 = new EloquentMorphResetModelStub;
        $model1->id = 1;
        $model2 = new EloquentMorphResetModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    /**
     * Note that the tests are the exact same for morph many because the classes share this code...
     * Will still test to be safe.
     */
    public function testMorphManySetsProperConstraints()
    {
        $this->getManyRelation();
    }

    public function testMorphManyEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getManyRelation();
        $relation->getParent()->expects('getKeyName')->returns('id');
        $relation->getParent()->expects('getKeyType')->returns('int');
        $relation->getQuery()->expects('whereIntegerInRaw')->with('table.morph_id', [1, 2]);
        $relation->getQuery()->expects('where')->with('table.morph_type', get_class($relation->getParent()));

        $model1 = new EloquentMorphResetModelStub;
        $model1->id = 1;
        $model2 = new EloquentMorphResetModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testMorphRelationUpsertFillsForeignKey()
    {
        $relation = $this->getManyRelation();

        $relation->getQuery()->expects('upsert')->with([
                ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey(), $relation->getMorphType() => $relation->getMorphClass()],
            ],
            ['email'],
            ['name']);

        $relation->upsert(
            ['email' => 'foo3', 'name' => 'bar'],
            ['email'],
            ['name']
        );

        $relation->getQuery()->expects('upsert')->with([
                ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey(), $relation->getMorphType() => $relation->getMorphClass()],
                ['name' => 'bar2', 'email' => 'foo2', $relation->getForeignKeyName() => $relation->getParentKey(), $relation->getMorphType() => $relation->getMorphClass()],
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

    public function testMakeFunctionOnMorph()
    {
        $_SERVER['__eloquent.saved'] = false;
        // Doesn't matter which relation type we use since they share the code...
        $relation = $this->getOneRelation();
        $instance = TestDouble::for(Model::class);
        $instance->expects('setAttribute')->with('morph_id', 1);
        $instance->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $instance->expects('save')->never();
        $relation->getRelated()->expects('newInstance')->with(['name' => 'taylor'])->returns($instance);

        $this->assertEquals($instance, $relation->make(['name' => 'taylor']));
    }

    public function testCreateFunctionOnMorph()
    {
        // Doesn't matter which relation type we use since they share the code...
        $relation = $this->getOneRelation();
        $created = TestDouble::for(Model::class);
        $created->expects('setAttribute')->with('morph_id', 1);
        $created->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $relation->getRelated()->expects('newInstance')->with(['name' => 'taylor'])->returns($created);
        $created->expects('save')->returns(true);

        $this->assertEquals($created, $relation->create(['name' => 'taylor']));
    }

    public function testFindOrNewMethodFindsModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('find')->with('foo', ['*'])->returns($model = TestDouble::for(Model::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->findOrNew('foo'));
    }

    public function testFindOrNewMethodReturnsNewModelWithMorphKeysSet()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('find')->with('foo', ['*'])->returns(null);
        $relation->getRelated()->expects('newInstance')->with()->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->findOrNew('foo'));
    }

    public function testFirstOrNewMethodFindsFirstModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(Model::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrNew(['foo']));
    }

    public function testFirstOrNewMethodWithValueFindsFirstModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(Model::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testFirstOrNewMethodReturnsNewModelWithMorphKeysSet()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getRelated()->expects('newInstance')->with(['foo'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrNew(['foo']));
    }

    public function testFirstOrNewMethodWithValuesReturnsNewModelWithMorphKeysSet()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getRelated()->expects('newInstance')->with(['foo' => 'bar', 'baz' => 'qux'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testFirstOrCreateMethodFindsFirstModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(Model::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrCreate(['foo']));
    }

    public function testFirstOrCreateMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(Model::class));
        $relation->getRelated()->expects('newInstance')->never();
        $model->expects('setAttribute')->never();
        $model->expects('save')->never();

        $this->assertInstanceOf(Model::class, $relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testFirstOrCreateMethodCreatesNewMorphModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(fn ($scope) => $scope());
        $relation->getRelated()->expects('newInstance')->with(['foo'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->returns(true);

        $this->assertInstanceOf(Model::class, $relation->firstOrCreate(['foo']));
    }

    public function testFirstOrCreateMethodWithValuesCreatesNewMorphModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(fn ($scope) => $scope());
        $relation->getRelated()->expects('newInstance')->with(['foo' => 'bar', 'baz' => 'qux'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->returns(true);

        $this->assertInstanceOf(Model::class, $relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testCreateOrFirstMethodFindsFirstModel()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('newInstance')->with(['foo'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->throws(new UniqueConstraintViolationException('mysql', 'example mysql', [], new Exception('SQLSTATE[23000]: Integrity constraint violation: 1062')));

        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('useWritePdo')->returns($relation->getQuery());
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(Model::class));

        $this->assertInstanceOf(Model::class, $relation->createOrFirst(['foo']));
    }

    public function testCreateOrFirstMethodWithValuesFindsFirstModel()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('newInstance')->with(['foo' => 'bar', 'baz' => 'qux'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->throws(new UniqueConstraintViolationException('mysql', 'example mysql', [], new Exception('SQLSTATE[23000]: Integrity constraint violation: 1062')));

        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('useWritePdo')->returns($relation->getQuery());
        $relation->getQuery()->expects('where')->with(['foo' => 'bar'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(Model::class));

        $this->assertInstanceOf(Model::class, $relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testCreateOrFirstMethodCreatesNewMorphModel()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('newInstance')->with(['foo'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->returns(true);

        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('where')->never();
        $relation->getQuery()->expects('first')->never();

        $this->assertInstanceOf(Model::class, $relation->createOrFirst(['foo']));
    }

    public function testCreateOrFirstMethodWithValuesCreatesNewMorphModel()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('newInstance')->with(['foo' => 'bar', 'baz' => 'qux'])->returns($model = TestDouble::for(Model::class));
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->returns(true);

        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('where')->never();
        $relation->getQuery()->expects('first')->never();

        $this->assertInstanceOf(Model::class, $relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']));
    }

    public function testUpdateOrCreateMethodFindsFirstModelAndUpdates()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns($model = TestDouble::for(Model::class));
        $relation->getRelated()->expects('newInstance')->never();

        $model->wasRecentlyCreated = false;
        $model->expects('setAttribute')->never();
        $model->expects('fill')->with(['bar'])->returns($model);
        $model->expects('save');

        $this->assertInstanceOf(Model::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testUpdateOrCreateMethodCreatesNewMorphModel()
    {
        $relation = $this->getOneRelation();
        $relation->getQuery()->expects('withSavepointIfNeeded')->resolves(function ($scope) {
            return $scope();
        });
        $relation->getQuery()->expects('where')->with(['foo'])->returns($relation->getQuery());
        $relation->getQuery()->expects('first')->with()->returns(null);
        $relation->getRelated()->expects('newInstance')->with(['foo', 'bar'])->returns($model = TestDouble::for(Model::class));

        $model->wasRecentlyCreated = true;
        $model->expects('setAttribute')->with('morph_id', 1);
        $model->expects('setAttribute')->with('morph_type', get_class($relation->getParent()));
        $model->expects('save')->returns(true);

        $this->assertInstanceOf(Model::class, $relation->updateOrCreate(['foo'], ['bar']));
    }

    public function testCreateFunctionOnNamespacedMorph()
    {
        $relation = $this->getNamespacedRelation('namespace');
        $created = TestDouble::for(Model::class);
        $created->expects('setAttribute')->with('morph_id', 1);
        $created->expects('setAttribute')->with('morph_type', 'namespace');
        $relation->getRelated()->expects('newInstance')->with(['name' => 'taylor'])->returns($created);
        $created->expects('save')->returns(true);

        $this->assertEquals($created, $relation->create(['name' => 'taylor']));
    }

    public function testIsNotNull()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('getTable')->never();
        $relation->getRelated()->expects('getConnectionName')->never();

        $this->assertFalse($relation->is(null));
    }

    public function testIsModel()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('getTable')->returns('table');
        $relation->getRelated()->expects('getConnectionName')->returns('connection');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('morph_id')->returns(1);
        $model->expects('getTable')->returns('table');
        $model->expects('getConnectionName')->returns('connection');

        $this->assertTrue($relation->is($model));
    }

    public function testIsModelWithStringRelatedKey()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('getTable')->returns('table');
        $relation->getRelated()->expects('getConnectionName')->returns('connection');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('morph_id')->returns('1');
        $model->expects('getTable')->returns('table');
        $model->expects('getConnectionName')->returns('connection');

        $this->assertTrue($relation->is($model));
    }

    public function testIsNotModelWithNullRelatedKey()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('getTable')->never();
        $relation->getRelated()->expects('getConnectionName')->never();

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('morph_id')->returns(null);
        $model->expects('getTable')->never();
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherRelatedKey()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('getTable')->never();
        $relation->getRelated()->expects('getConnectionName')->never();

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('morph_id')->returns(2);
        $model->expects('getTable')->never();
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherTable()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('getTable')->returns('table');
        $relation->getRelated()->expects('getConnectionName')->never();

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('morph_id')->returns(1);
        $model->expects('getTable')->returns('table.two');
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherConnection()
    {
        $relation = $this->getOneRelation();

        $relation->getRelated()->expects('getTable')->returns('table');
        $relation->getRelated()->expects('getConnectionName')->returns('connection');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('morph_id')->returns(1);
        $model->expects('getTable')->returns('table');
        $model->expects('getConnectionName')->returns('connection.two');

        $this->assertFalse($relation->is($model));
    }

    protected function getOneRelation()
    {
        $queryBuilder = TestDouble::for(QueryBuilder::class);
        $builder = TestDouble::for(new Builder($queryBuilder));
        $builder->expects('whereNotNull')->with('table.morph_id');
        $builder->expects('where')->with('table.morph_id', '=', 1);
        $related = TestDouble::for(Model::class);
        $builder->allows('getModel')->returns($related);
        $parent = TestDouble::for(Model::class);
        $parent->allows('getAttribute')->with('id')->returns(1);
        $parent->allows('getMorphClass')->returns(get_class($parent));
        $builder->expects('where')->with('table.morph_type', get_class($parent));

        return new MorphOne($builder, $parent, 'table.morph_type', 'table.morph_id', 'id');
    }

    protected function getManyRelation()
    {
        $builder = TestDouble::for(Builder::class);
        $builder->expects('whereNotNull')->with('table.morph_id');
        $builder->expects('where')->with('table.morph_id', '=', 1);
        $related = TestDouble::for(Model::class);
        $builder->allows('getModel')->returns($related);
        $parent = TestDouble::for(Model::class);
        $parent->allows('getAttribute')->with('id')->returns(1);
        $parent->allows('getMorphClass')->returns(get_class($parent));
        $builder->expects('where')->with('table.morph_type', get_class($parent));

        return new MorphMany($builder, $parent, 'table.morph_type', 'table.morph_id', 'id');
    }

    protected function getNamespacedRelation($alias)
    {
        require_once __DIR__.'/stubs/EloquentModelNamespacedStub.php';

        Relation::morphMap([
            $alias => EloquentModelNamespacedStub::class,
        ]);

        $builder = TestDouble::for(Builder::class);
        $builder->expects('whereNotNull')->with('table.morph_id');
        $builder->expects('where')->with('table.morph_id', '=', 1);
        $related = TestDouble::for(Model::class);
        $builder->allows('getModel')->returns($related);
        $parent = TestDouble::for(EloquentModelNamespacedStub::class);
        $parent->allows('getAttribute')->with('id')->returns(1);
        $parent->allows('getMorphClass')->returns($alias);
        $builder->expects('where')->with('table.morph_type', $alias);

        return new MorphOne($builder, $parent, 'table.morph_type', 'table.morph_id', 'id');
    }
}

class EloquentMorphResetModelStub extends Model
{
    //
}
