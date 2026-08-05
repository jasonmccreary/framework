<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Grammars\Grammar;
use PHPUnit\Framework\TestCase;
use stdClass;

class DatabaseEloquentBelongsToManyWithCastedAttributesTest extends TestCase
{
    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();
        $model1 = TestDouble::for(Model::class);
        $model1->shouldReceive('hasAttribute')->passthru();
        $model1->allows('getAttribute')->with('parent_key')->returns(1);
        $model1->shouldReceive('getAttribute')->with('foo')->passthru();
        $model1->allows('hasGetMutator')->returns(false);
        $model1->allows('hasAttributeMutator')->returns(false);
        $model1->allows('hasRelationAutoloadCallback')->returns(false);
        $model1->allows('getCasts')->returns([]);
        $model1->shouldReceive('getRelationValue', 'relationLoaded', 'relationResolver', 'setRelation', 'isRelation')->passthru();

        $model2 = TestDouble::for(Model::class);
        $model2->shouldReceive('hasAttribute')->passthru();
        $model2->allows('getAttribute')->with('parent_key')->returns(2);
        $model2->shouldReceive('getAttribute')->with('foo')->passthru();
        $model2->allows('hasGetMutator')->returns(false);
        $model2->allows('hasAttributeMutator')->returns(false);
        $model2->allows('hasRelationAutoloadCallback')->returns(false);
        $model2->allows('getCasts')->returns([]);
        $model2->shouldReceive('getRelationValue', 'relationLoaded', 'relationResolver', 'setRelation', 'isRelation')->passthru();

        $result1 = (object) [
            'pivot' => (object) [
                'foreign_key' => new class
                {
                    public function __toString()
                    {
                        return '1';
                    }
                },
            ],
        ];

        $models = $relation->match([$model1, $model2], Collection::wrap($result1), 'foo');
        $this->assertNull($models[1]->foo);
        $this->assertSame(1, $models[0]->foo->count());
        $this->assertContains($result1, $models[0]->foo);
    }

    protected function getRelation()
    {
        $builder = TestDouble::for(Builder::class);
        $related = TestDouble::for(Model::class);
        $related->shouldReceive('newCollection')->passthru();
        $related->shouldReceive('resolveCollectionFromAttribute')->passthru();
        $builder->allows('getModel')->returns($related);
        $related->allows('qualifyColumn');
        $builder->shouldReceive('join', 'where');
        $grammar = TestDouble::for(Grammar::class);
        $grammar->allows('isExpression')->returns(false);

        $stdClass = TestDouble::for(stdClass::class);
        $stdClass->allows('getGrammar')->returns($grammar);

        $builder->allows('getQuery')->returns($stdClass);

        return new BelongsToMany(
            $builder,
            new EloquentBelongsToManyModelStub,
            'relation',
            'foreign_key',
            'id',
            'parent_key',
            'related_key'
        );
    }
}

class EloquentBelongsToManyModelStub extends Model
{
    public $foreign_key = 'foreign.value';
}
