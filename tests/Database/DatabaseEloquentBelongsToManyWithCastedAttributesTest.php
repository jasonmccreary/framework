<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Grammars\Grammar;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;
use stdClass;

class DatabaseEloquentBelongsToManyWithCastedAttributesTest extends TestCase
{
    use VerifiesDoubles;

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();
        $model1 = TestDouble::for(Model::class)->passthru(new class extends Model
        {
        });
        $model1->allows('getAttribute')->with('parent_key')->returns(1);

        $model2 = TestDouble::for(Model::class)->passthru(new class extends Model
        {
        });
        $model2->allows('getAttribute')->with('parent_key')->returns(2);

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
        $related = TestDouble::for(Model::class)->passthru(new class extends Model
        {
        });
        $builder->allows('getModel')->returns($related);
        $related->allows('qualifyColumn');
        $builder->allows('join');
        $builder->allows('where');
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
