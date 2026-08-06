<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Tests\Database\stubs\TestEnum;
use PHPUnit\Framework\TestCase;

class DatabaseEloquentMorphToTest extends TestCase
{
    protected $builder;

    protected $related;

    public function testLookupDictionaryIsProperlyConstructedForEnums()
    {
        $relation = $this->getRelation();
        $relation->addEagerConstraints([
            $one = (object) ['morph_type' => 'morph_type_2', 'foreign_key' => TestEnum::test],
        ]);
        $dictionary = $relation->getDictionary();
        $relation->getDictionary();
        $enumKey = TestEnum::test;
        if (isset($enumKey->value)) {
            $value = $dictionary['morph_type_2'][$enumKey->value][0]->foreign_key;
            $this->assertEquals(TestEnum::test, $value);
        } else {
            $this->fail('An enum should contain value property');
        }
    }

    public function testLookupDictionaryIsProperlyConstructed()
    {
        $stringish = new class
        {
            public function __toString()
            {
                return 'foreign_key_2';
            }
        };

        $relation = $this->getRelation();
        $relation->addEagerConstraints([
            $one = (object) ['morph_type' => 'morph_type_1', 'foreign_key' => 'foreign_key_1'],
            $two = (object) ['morph_type' => 'morph_type_1', 'foreign_key' => 'foreign_key_1'],
            $three = (object) ['morph_type' => 'morph_type_2', 'foreign_key' => 'foreign_key_2'],
            $four = (object) ['morph_type' => 'morph_type_2', 'foreign_key' => $stringish],
        ]);

        $dictionary = $relation->getDictionary();

        $this->assertEquals([
            'morph_type_1' => [
                'foreign_key_1' => [
                    $one,
                    $two,
                ],
            ],
            'morph_type_2' => [
                'foreign_key_2' => [
                    $three,
                    $four,
                ],
            ],
        ], $dictionary);
    }

    public function testMorphToWithDefault()
    {
        $relation = $this->getRelation()->withDefault();

        $this->builder->expects('first')->returns(null);

        $newModel = new EloquentMorphToModelStub;

        $this->assertEquals($newModel, $relation->getResults());
    }

    public function testMorphToWithDynamicDefault()
    {
        $relation = $this->getRelation()->withDefault(function ($newModel) {
            $newModel->username = 'taylor';
        });

        $this->builder->expects('first')->returns(null);

        $newModel = new EloquentMorphToModelStub;
        $newModel->username = 'taylor';

        $result = $relation->getResults();

        $this->assertEquals($newModel, $result);

        $this->assertSame('taylor', $result->username);
    }

    public function testMorphToWithArrayDefault()
    {
        $relation = $this->getRelation()->withDefault(['username' => 'taylor']);

        $this->builder->expects('first')->returns(null);

        $newModel = new EloquentMorphToModelStub;
        $newModel->username = 'taylor';

        $result = $relation->getResults();

        $this->assertEquals($newModel, $result);

        $this->assertSame('taylor', $result->username);
    }

    public function testMorphToWithZeroMorphType()
    {
        $parent = $this->getMockBuilder(EloquentMorphToModelStub::class)->onlyMethods(['getAttributeFromArray', 'morphEagerTo', 'morphInstanceTo'])->getMock();
        $parent->method('getAttributeFromArray')->with('relation_type')->willReturn(0);
        $parent->expects($this->once())->method('morphInstanceTo');
        $parent->expects($this->never())->method('morphEagerTo');

        $parent->relation();
    }

    public function testMorphToWithEmptyStringMorphType()
    {
        $parent = $this->getMockBuilder(EloquentMorphToModelStub::class)->onlyMethods(['getAttributeFromArray', 'morphEagerTo', 'morphInstanceTo'])->getMock();
        $parent->method('getAttributeFromArray')->with('relation_type')->willReturn('');
        $parent->expects($this->once())->method('morphEagerTo');
        $parent->expects($this->never())->method('morphInstanceTo');

        $parent->relation();
    }

    public function testMorphToWithSpecifiedClassDefault()
    {
        $parent = new EloquentMorphToModelStub;
        $parent->relation_type = EloquentMorphToRelatedStub::class;

        $relation = $parent->relation()->withDefault();

        $newModel = new EloquentMorphToRelatedStub;

        $result = $relation->getResults();

        $this->assertEquals($newModel, $result);
    }

    public function testAssociateMethodSetsForeignKeyAndTypeOnModel()
    {
        $parent = TestDouble::for(Model::class);
        $parent->allows('getAttribute')->with('foreign_key')->returns('foreign.value');

        $relation = $this->getRelationAssociate($parent);

        $associate = TestDouble::for(Model::class);
        $associate->allows('getAttribute')->returns(1);
        $associate->allows('getMorphClass')->returns('Model');

        $parent->expects('setAttribute')->with('foreign_key', 1);
        $parent->expects('setAttribute')->with('morph_type', 'Model');
        $parent->expects('setRelation')->with('relation', $associate);

        $relation->associate($associate);
    }

    public function testAssociateMethodIgnoresNullValue()
    {
        $parent = TestDouble::for(Model::class);
        $parent->expects('getAttribute')->with('foreign_key')->returns('foreign.value');

        $relation = $this->getRelationAssociate($parent);

        $parent->expects('setAttribute')->with('foreign_key', null);
        $parent->expects('setAttribute')->with('morph_type', null);
        $parent->expects('setRelation')->with('relation', null);

        $relation->associate(null);
    }

    public function testDissociateMethodDeletesUnsetsKeyAndTypeOnModel()
    {
        $parent = TestDouble::for(Model::class);
        $parent->expects('getAttribute')->with('foreign_key')->returns('foreign.value');

        $relation = $this->getRelation($parent);

        $parent->expects('setAttribute')->with('foreign_key', null);
        $parent->expects('setAttribute')->with('morph_type', null);
        $parent->expects('setRelation')->with('relation', null);

        $relation->dissociate();
    }

    public function testIsNotNull()
    {
        $relation = $this->getRelation();

        $relation->getRelated()->expects('getTable')->never();
        $relation->getRelated()->expects('getConnectionName')->never();

        $this->assertFalse($relation->is(null));
    }

    public function testIsModel()
    {
        $relation = $this->getRelation();

        $this->related->expects('getConnectionName')->returns('relation');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns('foreign.value');
        $model->expects('getTable')->returns('relation');
        $model->expects('getConnectionName')->returns('relation');

        $this->assertTrue($relation->is($model));
    }

    public function testIsModelWithIntegerParentKey()
    {
        $parent = TestDouble::for(Model::class);
        // when addConstraints is called we need to return the foreign value
        $parent->expects('getAttribute')->with('foreign_key')->returns('foreign.value');
        // when getParentKey is called we want to return an integer
        $parent->expects('getAttribute')->with('foreign_key')->returns(1);

        $relation = $this->getRelation($parent);

        $this->related->expects('getConnectionName')->returns('relation');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns('1');
        $model->expects('getTable')->returns('relation');
        $model->expects('getConnectionName')->returns('relation');

        $this->assertTrue($relation->is($model));
    }

    public function testIsModelWithIntegerRelatedKey()
    {
        $parent = TestDouble::for(Model::class);
        // when addConstraints is called we need to return the foreign value
        $parent->expects('getAttribute')->with('foreign_key')->returns('foreign.value');
        // when getParentKey is called we want to return a string
        $parent->expects('getAttribute')->with('foreign_key')->returns('1');

        $relation = $this->getRelation($parent);

        $this->related->expects('getConnectionName')->returns('relation');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns(1);
        $model->expects('getTable')->returns('relation');
        $model->expects('getConnectionName')->returns('relation');

        $this->assertTrue($relation->is($model));
    }

    public function testIsModelWithIntegerKeys()
    {
        $parent = TestDouble::for(Model::class);

        // when addConstraints is called we need to return the foreign value
        $parent->expects('getAttribute')->with('foreign_key')->returns('foreign.value');
        // when getParentKey is called we want to return an integer
        $parent->expects('getAttribute')->with('foreign_key')->returns(1);

        $relation = $this->getRelation($parent);

        $this->related->expects('getConnectionName')->returns('relation');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns(1);
        $model->expects('getTable')->returns('relation');
        $model->expects('getConnectionName')->returns('relation');

        $this->assertTrue($relation->is($model));
    }

    public function testIsNotModelWithNullParentKey()
    {
        $parent = TestDouble::for(Model::class);

        // when addConstraints is called we need to return the foreign value
        $parent->expects('getAttribute')->with('foreign_key')->returns('foreign.value');
        // when getParentKey is called we want to return null

        $parent->expects('getAttribute')->with('foreign_key')->returns(null);

        $relation = $this->getRelation($parent);

        $this->related->expects('getConnectionName')->never();

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns('foreign.value');
        $model->expects('getTable')->never();
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithNullRelatedKey()
    {
        $relation = $this->getRelation();

        $this->related->expects('getConnectionName')->never();

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns(null);
        $model->expects('getTable')->never();
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherKey()
    {
        $relation = $this->getRelation();

        $this->related->expects('getConnectionName')->never();

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns('foreign.value.two');
        $model->expects('getTable')->never();
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherTable()
    {
        $relation = $this->getRelation();

        $this->related->expects('getConnectionName')->never();

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns('foreign.value');
        $model->expects('getTable')->returns('table.two');
        $model->expects('getConnectionName')->never();

        $this->assertFalse($relation->is($model));
    }

    public function testIsNotModelWithAnotherConnection()
    {
        $relation = $this->getRelation();

        $this->related->expects('getConnectionName')->returns('relation');

        $model = TestDouble::for(Model::class);
        $model->expects('getAttribute')->with('id')->returns('foreign.value');
        $model->expects('getTable')->returns('relation');
        $model->expects('getConnectionName')->returns('relation.two');

        $this->assertFalse($relation->is($model));
    }

    public function testMatchToMorphParentsNormalizesKeyWhenOwnerKeyIsNullAndResultKeyIsObject()
    {
        $uuidObject = new class
        {
            public function __toString(): string
            {
                return 'uuid-value';
            }
        };

        $builder = TestDouble::for(Builder::class);
        $related = TestDouble::for(Model::class);
        $builder->allows('getModel')->returns($related);

        $parent = new EloquentMorphToModelStub;
        $parent->morph_type = 'type_1';
        $parent->foreign_key = 'uuid-value';

        $relation = Relation::noConstraints(function () use ($builder, $parent) {
            return new EloquentMorphToAccessibleStub($builder, $parent, 'foreign_key', null, 'morph_type', 'relation');
        });

        $relation->addEagerConstraints([$parent]);

        $result = TestDouble::for(Model::class);
        $result->expects('getKey')->returns($uuidObject);

        $relation->callMatchToMorphParents('type_1', new EloquentCollection([$result]));

        $this->assertSame($result, $parent->getRelation('relation'));
    }

    protected function getRelationAssociate($parent)
    {
        $builder = TestDouble::for(Builder::class);
        $builder->allows('where')->with('relation.id', '=', 'foreign.value');
        $related = TestDouble::for(Model::class);
        $related->allows('getKey')->returns(1);
        $related->allows('getTable')->returns('relation');
        $related->allows('qualifyColumn')->resolves(fn (string $column) => "relation.{$column}");
        $builder->allows('getModel')->returns($related);

        return new MorphTo($builder, $parent, 'foreign_key', 'id', 'morph_type', 'relation');
    }

    public function getRelation($parent = null, $builder = null)
    {
        $this->builder = $builder ?: TestDouble::for(Builder::class);
        $this->builder->allows('where')->with('relation.id', '=', 'foreign.value');
        $this->related = TestDouble::for(Model::class);
        $this->related->allows('getKeyName')->returns('id');
        $this->related->allows('getTable')->returns('relation');
        $this->related->allows('qualifyColumn')->resolves(fn (string $column) => "relation.{$column}");
        $this->builder->allows('getModel')->returns($this->related);
        $parent = $parent ?: new EloquentMorphToModelStub;

        return TestDouble::for(MorphTo::class)->passthru(new MorphTo($this->builder, $parent, 'foreign_key', 'id', 'morph_type', 'relation'));
    }
}

class EloquentMorphToModelStub extends Model
{
    public $foreign_key = 'foreign.value';

    public $table = 'eloquent_morph_to_model_stubs';

    public function relation()
    {
        return $this->morphTo();
    }
}

class EloquentMorphToRelatedStub extends Model
{
    public $table = 'eloquent_morph_to_related_stubs';
}

class EloquentMorphToAccessibleStub extends MorphTo
{
    public function callMatchToMorphParents($type, EloquentCollection $results): void
    {
        $this->matchToMorphParents($type, $results);
    }
}
