<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\Double;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Mockery;
use PHPUnit\Framework\TestCase;

class EloquentHasOneOrManyDeprecationTest extends TestCase
{
    public function testHasManyMatchWithNullLocalKey(): void
    {
        $relation = $this->getHasManyRelation();

        $result1 = new HasOneOrManyDeprecationModelStub;
        $result1->foreign_key = 1;

        $result2 = new HasOneOrManyDeprecationModelStub;
        $result2->foreign_key = '';

        $model1 = new HasOneOrManyDeprecationModelStub;
        $model1->id = 1;
        $model2 = new HasOneOrManyDeprecationModelStub;
        $model2->id = null;

        $relation->getRelated()->expects('newCollection')->resolves(function ($array) {
            return new Collection($array);
        });

        $models = $relation->match([$model1, $model2], new Collection([$result1, $result2]), 'foo');

        $this->assertCount(1, $models[0]->foo);
        $this->assertNull($models[1]->foo);
    }

    public function testHasOneMatchWithNullLocalKey(): void
    {
        $relation = $this->getHasOneRelation();

        $result1 = new HasOneOrManyDeprecationModelStub;
        $result1->foreign_key = 1;

        $model1 = new HasOneOrManyDeprecationModelStub;
        $model1->id = 1;
        $model2 = new HasOneOrManyDeprecationModelStub;
        $model2->id = null;

        $models = $relation->match([$model1, $model2], new Collection([$result1]), 'foo');

        $this->assertInstanceOf(HasOneOrManyDeprecationModelStub::class, $models[0]->foo);
        $this->assertNull($models[1]->foo);
    }

    protected function getHasManyRelation(): HasMany
    {
        $queryBuilder = Double::for(QueryBuilder::class);
        $builder = Double::for(new Builder($queryBuilder));
        $builder->expects('whereNotNull')->with('table.foreign_key');
        $builder->expects('where')->with('table.foreign_key', '=', 1);
        $related = Double::for(Model::class);
        $builder->expects('getModel')->returns($related);
        $parent = Double::for(Model::class);
        $parent->expects('getAttribute')->with('id')->returns(1);

        return new HasMany($builder, $parent, 'table.foreign_key', 'id');
    }

    protected function getHasOneRelation(): HasOne
    {
        $queryBuilder = Double::for(QueryBuilder::class);
        $builder = Double::for(new Builder($queryBuilder));
        $builder->expects('whereNotNull')->with('table.foreign_key');
        $builder->expects('where')->with('table.foreign_key', '=', 1);
        $related = Double::for(Model::class);
        $builder->expects('getModel')->returns($related);
        $parent = Double::for(Model::class);
        $parent->expects('getAttribute')->with('id')->returns(1);

        return new HasOne($builder, $parent, 'table.foreign_key', 'id');
    }
}

class HasOneOrManyDeprecationModelStub extends Model
{
    public $foreign_key;
}
