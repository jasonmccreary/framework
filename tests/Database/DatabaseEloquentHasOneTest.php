<?php

namespace Illuminate\Tests\Database;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;
use JMac\Testing\Matching\Argument;

class DatabaseEloquentHasOneTest extends TestCase
{
    protected $builder;

    protected $related;

    protected $parent;

    public function testHasOneWithDefault()
    {
        $relation = $this->getRelation()->withDefault();

        $this->builder->expects('first')->returns(null);

        $newModel = new EloquentHasOneModelStub;

        $this->related->expects('newInstance')->returns($newModel);

        $this->assertSame($newModel, $relation->getResults());

        $this->assertSame(1, $newModel->getAttribute('foreign_key'));
    }

    public function testHasOneWithDynamicDefault()
    {
        $relation = $this->getRelation()->withDefault(function ($newModel) {
            $newModel->username = 'taylor';
        });

        $this->builder->expects('first')->returns(null);

        $newModel = new EloquentHasOneModelStub;

        $this->related->expects('newInstance')->returns($newModel);

        $this->assertSame($newModel, $relation->getResults());

        $this->assertSame('taylor', $newModel->username);

        $this->assertSame(1, $newModel->getAttribute('foreign_key'));
    }

    public function testHasOneWithDynamicDefaultUseParentModel()
    {
        $relation = $this->getRelation()->withDefault(function ($newModel, $parentModel) {
            $newModel->username = $parentModel->username;
        });

        $this->builder->expects('first')->returns(null);

        $newModel = new EloquentHasOneModelStub;

        $this->related->expects('newInstance')->returns($newModel);

        $this->assertSame($newModel, $relation->getResults());

        $this->assertSame('taylor', $newModel->username);

        $this->assertSame(1, $newModel->getAttribute('foreign_key'));
    }

    public function testHasOneWithArrayDefault()
    {
        $attributes = ['username' => 'taylor'];

        $relation = $this->getRelation()->withDefault($attributes);

        $this->builder->expects('first')->returns(null);

        $newModel = new EloquentHasOneModelStub;

        $this->related->expects('newInstance')->returns($newModel);

        $this->assertSame($newModel, $relation->getResults());

        $this->assertSame('taylor', $newModel->username);

        $this->assertSame(1, $newModel->getAttribute('foreign_key'));
    }

    public function testMakeMethodDoesNotSaveNewModel()
    {
        $relation = $this->getRelation();
        $instance = $this->getMockBuilder(Model::class)->onlyMethods(['save', 'newInstance', 'setAttribute'])->getMock();
        $relation->getRelated()->expects('newInstance')->with(['name' => 'taylor'])->returns($instance);
        $instance->expects($this->once())->method('setAttribute')->with('foreign_key', 1);
        $instance->expects($this->never())->method('save');

        $this->assertEquals($instance, $relation->make(['name' => 'taylor']));
    }

    public function testSaveMethodSetsForeignKeyOnModel()
    {
        $relation = $this->getRelation();
        $mockModel = $this->getMockBuilder(Model::class)->onlyMethods(['save'])->getMock();
        $mockModel->expects($this->once())->method('save')->willReturn(true);
        $result = $relation->save($mockModel);

        $attributes = $result->getAttributes();
        $this->assertEquals(1, $attributes['foreign_key']);
    }

    public function testCreateMethodProperlyCreatesNewModel()
    {
        $relation = $this->getRelation();
        $created = $this->getMockBuilder(Model::class)->onlyMethods(['save', 'getKey', 'setAttribute'])->getMock();
        $created->expects($this->once())->method('save')->willReturn(true);
        $relation->getRelated()->expects('newInstance')->with(['name' => 'taylor'])->returns($created);
        $created->expects($this->once())->method('setAttribute')->with('foreign_key', 1);

        $this->assertEquals($created, $relation->create(['name' => 'taylor']));
    }

    public function testForceCreateMethodProperlyCreatesNewModel()
    {
        $relation = $this->getRelation();
        $attributes = ['name' => 'taylor', $relation->getForeignKeyName() => $relation->getParentKey()];

        $created = Double::for(Model::class);
        $created->expects('getAttribute')->with($relation->getForeignKeyName())->returns($relation->getParentKey());

        $query = Double::for(Builder::class);
        $query->expects('forceCreate')->with($attributes)->returns($created);
        $related = $relation->getRelated();
        $related->allows('newQuery')->returns($query);
        // Model::__call() forwards undeclared methods (like forceCreate) to
        // newQuery() via forwardCallTo() — but forwardCallTo() is itself a
        // proxied method on this double, so it never runs for real. Stub it
        // to forward to $query directly instead.
        $related->allows('forwardCallTo')->resolves(
            fn ($object, $method, $parameters) => $query->{$method}(...$parameters)
        );

        $this->assertEquals($created, $relation->forceCreate(['name' => 'taylor']));
        $this->assertEquals(1, $created->getAttribute('foreign_key'));
    }

    public function testRelationIsProperlyInitialized()
    {
        $relation = $this->getRelation();
        $model = Double::for(Model::class);
        $model->expects('setRelation')->with('foo', null);
        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getRelation();
        $relation->getParent()->expects('getKeyName')->returns('id');
        $relation->getParent()->expects('getKeyType')->returns('int');
        $relation->getQuery()->getQuery()->expects('whereIntegerInRaw')->with('table.foreign_key', [1, 2]);
        $model1 = new EloquentHasOneModelStub;
        $model1->id = 1;
        $model2 = new EloquentHasOneModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();

        $result1 = new EloquentHasOneModelStub;
        $result1->foreign_key = 1;
        $result2 = new EloquentHasOneModelStub;
        $result2->foreign_key = 2;
        $result3 = new EloquentHasOneModelStub;
        $result3->foreign_key = new class
        {
            public function __toString()
            {
                return '4';
            }
        };

        $model1 = new EloquentHasOneModelStub;
        $model1->id = 1;
        $model2 = new EloquentHasOneModelStub;
        $model2->id = 2;
        $model3 = new EloquentHasOneModelStub;
        $model3->id = 3;
        $model4 = new EloquentHasOneModelStub;
        $model4->id = 4;

        $models = $relation->match([$model1, $model2, $model3, $model4], new Collection([$result1, $result2, $result3]), 'foo');

        $this->assertEquals(1, $models[0]->foo->foreign_key);
        $this->assertEquals(2, $models[1]->foo->foreign_key);
        $this->assertNull($models[2]->foo);
        $this->assertSame('4', (string) $models[3]->foo->foreign_key);
    }

    public function testRelationCountQueryCanBeBuilt()
    {
        $relation = $this->getRelation();
        $builder = Double::for(Builder::class);

        $baseQuery = Double::for(BaseBuilder::class);
        $baseQuery->from = 'one';
        $parentQuery = Double::for(BaseBuilder::class);
        $parentQuery->from = 'two';

        $builder->expects('getQuery')->returns($baseQuery);
        $builder->expects('getQuery')->returns($parentQuery);

        // select()/whereColumn()/setBindings() are all forwarded via Eloquent
        // Builder's real __call(), which always `return $this;` regardless of
        // what forwardCallTo() itself returns (chainable-builder semantics) —
        // and forwardCallTo() is itself a proxied method on this bare double,
        // so it never runs for real either way. Stub it directly per method.
        $builder->expects('forwardCallTo')->with(Argument::any(), 'select', Argument::any())
            ->resolves(function ($object, $method, $parameters) {
                $this->assertInstanceOf(Expression::class, $parameters[0]);
            });
        $relation->getParent()->expects('qualifyColumn')->returns('table.id');
        $builder->expects('forwardCallTo')->with(Argument::any(), 'whereColumn', ['table.id', '=', 'table.foreign_key']);
        $builder->expects('forwardCallTo')->with(Argument::any(), 'setBindings', [[], 'select']);

        $relation->getRelationExistenceCountQuery($builder, $builder);
    }

    public function testIsNotNull()
    {
        $relation = $this->getRelation();

        $this->related->expects('getTable')->never();
        $this->related->expects('getConnectionName')->never();

        $this->assertFalse($relation->is(null));
    }

    public function testIsModel()
    {
        $relation = $this->getRelation();

        $this->related->expects('getTable')->returns('table');
        $this->related->expects('getConnectionName')->returns('connection');

        $model = Double::for(Model::class);
        $model->expects('getAttribute')->with('foreign_key')->returns(1);
        $model->expects('getTable')->returns('table');
        $model->expects('getConnectionName')->returns('connection');

        $this->assertTrue($relation->is($model));
    }

    public function testIsModelWithStringRelatedKey()
    {
        $relation = $this->getRelation();

        $this->related->expects('getTable')->returns('table');
        $this->related->expects('getConnectionName')->returns('connection');

        $model = Double::for(Model::class);
        $model->expects('getAttribute')->with('foreign_key')->returns('1');
        $model->expects('getTable')->returns('table');
        $model->expects('getConnectionName')->returns('connection');

        $this->assertTrue($relation->is($model));
    }

    public function testIsNotModelWithNullRelatedKey()
    {
        $relation = $this->getRelation();

        $this->related->expects('getTable')->never();
        $this->related->expects('getConnectionName')->never();

        $model = Double::for(Model::class);
        $model->expects('getAttribute')->with('foreign_key')->returns(null);
        $model->expects('getTable')->never();
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherRelatedKey()
    {
        $relation = $this->getRelation();

        $this->related->expects('getTable')->never();
        $this->related->expects('getConnectionName')->never();

        $model = Double::for(Model::class);
        $model->expects('getAttribute')->with('foreign_key')->returns(2);
        $model->expects('getTable')->never();
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherTable()
    {
        $relation = $this->getRelation();

        $this->related->expects('getTable')->returns('table');
        $this->related->expects('getConnectionName')->never();

        $model = Double::for(Model::class);
        $model->expects('getAttribute')->with('foreign_key')->returns(1);
        $model->expects('getTable')->returns('table.two');
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherConnection()
    {
        $relation = $this->getRelation();

        $this->related->expects('getTable')->returns('table');
        $this->related->expects('getConnectionName')->returns('connection');

        $model = Double::for(Model::class);
        $model->expects('getAttribute')->with('foreign_key')->returns(1);
        $model->expects('getTable')->returns('table');
        $model->expects('getConnectionName')->returns('connection.two');

        $this->assertFalse($relation->is($model));
    }

    protected function getRelation()
    {
        $queryBuilder = Double::for(BaseBuilder::class);
        $queryBuilder->allows('whereNotNull')->with('table.foreign_key');
        $this->builder = Double::for(new Builder($queryBuilder));
        $this->builder->allows('where')->with('table.foreign_key', '=', 1);
        $this->builder->allows('getQuery')->returns($queryBuilder);
        // Eloquent Builder's own __call() forwards undeclared methods (e.g.
        // whereIntegerInRaw) to the underlying query builder via forwardCallTo() —
        // but forwardCallTo() is itself a proxied method on this double, so it
        // never runs for real and $this->query is never populated (Double::for()
        // doesn't run the real constructor). Stub it to forward to $queryBuilder
        // directly instead.
        $this->builder->allows('forwardCallTo')->resolves(
            fn ($object, $method, $parameters) => $queryBuilder->{$method}(...$parameters)
        );
        $this->related = Double::for(Model::class);
        $this->builder->allows('getModel')->returns($this->related);
        $this->parent = Double::for(Model::class);
        $this->parent->allows('getAttribute')->with('id')->returns(1);
        $this->parent->allows('getAttribute')->with('username')->returns('taylor');
        $this->parent->allows('getCreatedAtColumn')->returns('created_at');
        $this->parent->allows('getUpdatedAtColumn')->returns('updated_at');
        $this->parent->allows('newQueryWithoutScopes')->returns($this->builder);

        return new HasOne($this->builder, $this->parent, 'table.foreign_key', 'id');
    }
}

class EloquentHasOneModelStub extends Model
{
    public $foreign_key = 'foreign.value';
}
