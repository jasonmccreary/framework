<?php

namespace Illuminate\Tests\Database;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;

class DatabaseEloquentRelationTest extends TestCase
{
    public function testSetRelationFail()
    {
        $parent = new EloquentRelationResetModelStub;
        $relation = new EloquentRelationResetModelStub;
        $parent->setRelation('test', $relation);
        $parent->setRelation('foo', 'bar');
        $this->assertArrayNotHasKey('foo', $parent->toArray());
    }

    public function testUnsetExistingRelation()
    {
        $parent = new EloquentRelationResetModelStub;
        $relation = new EloquentRelationResetModelStub;
        $parent->setRelation('foo', $relation);
        $parent->unsetRelation('foo');
        $this->assertFalse($parent->relationLoaded('foo'));
    }

    public function testTouchMethodUpdatesRelatedTimestamps()
    {
        // whereNotNull() is forwarded via Eloquent Builder's own __call() to the
        // underlying query builder via forwardCallTo() — but forwardCallTo() is
        // itself a proxied method on a Double, so it never runs for real. Wire
        // $builder to a real query-builder double and stub forwardCallTo to
        // forward for real.
        $queryBuilder = Double::for(QueryBuilder::class);
        $queryBuilder->expects('whereNotNull');
        $builder = Double::for(new Builder($queryBuilder));
        $builder->allows('forwardCallTo')->resolves(
            fn ($object, $method, $parameters) => $queryBuilder->{$method}(...$parameters)
        );
        $parent = Double::for(Model::class);
        $parent->expects('getAttribute')->with('id')->returns(1);
        $related = Double::for(EloquentNoTouchingModelStub::class)->passthru();
        $builder->expects('getModel')->returns($related);
        $builder->expects('where');
        $builder->expects('withoutGlobalScopes')->returns($builder);
        $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
        $related->expects('getUpdatedAtColumn')->returns('updated_at');
        $now = Carbon::now();
        $related->expects('freshTimestampString')->returns($now);
        $builder->expects('update')->with(['updated_at' => $now]);

        $relation->touch();
    }

    public function testCanDisableParentTouchingForAllModels()
    {
        /** @var \Illuminate\Tests\Database\EloquentNoTouchingModelStub $related */
        $related = Double::for(EloquentNoTouchingModelStub::class)->passthru();
        $related->expects('getUpdatedAtColumn')->never();
        $related->expects('freshTimestampString')->never();

        $this->assertFalse($related::isIgnoringTouch());

        Model::withoutTouching(function () use ($related) {
            $this->assertTrue($related::isIgnoringTouch());

            $queryBuilder = Double::for(QueryBuilder::class);
            $queryBuilder->expects('whereNotNull');
            $builder = Double::for(new Builder($queryBuilder));
            $builder->allows('forwardCallTo')->resolves(
                fn ($object, $method, $parameters) => $queryBuilder->{$method}(...$parameters)
            );
            $parent = Double::for(Model::class);

            $parent->expects('getAttribute')->with('id')->returns(1);
            $builder->expects('getModel')->returns($related);
            $builder->expects('where');
            $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
            $builder->expects('update')->never();

            $relation->touch();
        });

        $this->assertFalse($related::isIgnoringTouch());
    }

    public function testCanDisableTouchingForSpecificModel()
    {
        $related = Double::for(EloquentNoTouchingModelStub::class)->passthru();
        $related->expects('getUpdatedAtColumn')->never();
        $related->expects('freshTimestampString')->never();

        $anotherRelated = Double::for(EloquentNoTouchingAnotherModelStub::class)->passthru();

        $this->assertFalse($related::isIgnoringTouch());
        $this->assertFalse($anotherRelated::isIgnoringTouch());

        EloquentNoTouchingModelStub::withoutTouching(function () use ($related, $anotherRelated) {
            $this->assertTrue($related::isIgnoringTouch());
            $this->assertFalse($anotherRelated::isIgnoringTouch());

            $queryBuilder = Double::for(QueryBuilder::class);
            $queryBuilder->expects('whereNotNull');
            $builder = Double::for(new Builder($queryBuilder));
            $builder->allows('forwardCallTo')->resolves(
                fn ($object, $method, $parameters) => $queryBuilder->{$method}(...$parameters)
            );
            $parent = Double::for(Model::class);

            $parent->expects('getAttribute')->with('id')->returns(1);
            $builder->expects('getModel')->returns($related);
            $builder->expects('where');
            $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
            $builder->expects('update')->never();

            $relation->touch();

            $anotherQueryBuilder = Double::for(QueryBuilder::class);
            $anotherQueryBuilder->expects('whereNotNull');
            $anotherBuilder = Double::for(new Builder($anotherQueryBuilder));
            $anotherBuilder->allows('forwardCallTo')->resolves(
                fn ($object, $method, $parameters) => $anotherQueryBuilder->{$method}(...$parameters)
            );
            $anotherParent = Double::for(Model::class);

            $anotherParent->expects('getAttribute')->with('id')->returns(2);
            $anotherBuilder->expects('getModel')->returns($anotherRelated);
            $anotherBuilder->expects('where');
            $anotherBuilder->expects('withoutGlobalScopes')->returns($anotherBuilder);
            $anotherRelation = new HasOne($anotherBuilder, $anotherParent, 'foreign_key', 'id');
            $now = Carbon::now();
            $anotherRelated->expects('freshTimestampString')->returns($now);
            $anotherBuilder->expects('update')->with(['updated_at' => $now]);

            $anotherRelation->touch();
        });

        $this->assertFalse($related::isIgnoringTouch());
        $this->assertFalse($anotherRelated::isIgnoringTouch());
    }

    public function testParentModelIsNotTouchedWhenChildModelIsIgnored()
    {
        $related = Double::for(EloquentNoTouchingModelStub::class)->passthru();
        $related->expects('getUpdatedAtColumn')->never();
        $related->expects('freshTimestampString')->never();

        $relatedChild = Double::for(EloquentNoTouchingChildModelStub::class)->passthru();
        $relatedChild->expects('getUpdatedAtColumn')->never();
        $relatedChild->expects('freshTimestampString')->never();

        $this->assertFalse($related::isIgnoringTouch());
        $this->assertFalse($relatedChild::isIgnoringTouch());

        EloquentNoTouchingModelStub::withoutTouching(function () use ($related, $relatedChild) {
            $this->assertTrue($related::isIgnoringTouch());
            $this->assertTrue($relatedChild::isIgnoringTouch());

            $queryBuilder = Double::for(QueryBuilder::class);
            $queryBuilder->expects('whereNotNull');
            $builder = Double::for(new Builder($queryBuilder));
            $builder->allows('forwardCallTo')->resolves(
                fn ($object, $method, $parameters) => $queryBuilder->{$method}(...$parameters)
            );
            $parent = Double::for(Model::class);

            $parent->expects('getAttribute')->with('id')->returns(1);
            $builder->expects('getModel')->returns($related);
            $builder->expects('where');
            $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
            $builder->expects('update')->never();

            $relation->touch();

            $anotherQueryBuilder = Double::for(QueryBuilder::class);
            $anotherQueryBuilder->expects('whereNotNull');
            $anotherBuilder = Double::for(new Builder($anotherQueryBuilder));
            $anotherBuilder->allows('forwardCallTo')->resolves(
                fn ($object, $method, $parameters) => $anotherQueryBuilder->{$method}(...$parameters)
            );
            $anotherParent = Double::for(Model::class);

            $anotherParent->expects('getAttribute')->with('id')->returns(2);
            $anotherBuilder->expects('getModel')->returns($relatedChild);
            $anotherBuilder->expects('where');
            $anotherRelation = new HasOne($anotherBuilder, $anotherParent, 'foreign_key', 'id');
            $anotherBuilder->expects('update')->never();

            $anotherRelation->touch();
        });

        $this->assertFalse($related::isIgnoringTouch());
        $this->assertFalse($relatedChild::isIgnoringTouch());
    }

    public function testIgnoredModelsStateIsResetWhenThereAreExceptions()
    {
        $related = Double::for(EloquentNoTouchingModelStub::class)->passthru();
        $related->expects('getUpdatedAtColumn')->never();
        $related->expects('freshTimestampString')->never();

        $relatedChild = Double::for(EloquentNoTouchingChildModelStub::class)->passthru();
        $relatedChild->expects('getUpdatedAtColumn')->never();
        $relatedChild->expects('freshTimestampString')->never();

        $this->assertFalse($related::isIgnoringTouch());
        $this->assertFalse($relatedChild::isIgnoringTouch());

        try {
            EloquentNoTouchingModelStub::withoutTouching(function () use ($related, $relatedChild) {
                $this->assertTrue($related::isIgnoringTouch());
                $this->assertTrue($relatedChild::isIgnoringTouch());

                throw new Exception;
            });

            $this->fail('Exception was not thrown');
        } catch (Exception) {
            // Does nothing.
        }

        $this->assertFalse($related::isIgnoringTouch());
        $this->assertFalse($relatedChild::isIgnoringTouch());
    }

    public function testSettingMorphMapWithNumericArrayUsesTheTableNames()
    {
        Relation::morphMap([EloquentRelationResetModelStub::class]);

        $this->assertEquals([
            'reset' => EloquentRelationResetModelStub::class,
        ], Relation::morphMap());

        Relation::morphMap([], false);
    }

    public function testSettingMorphMapWithNumericKeys()
    {
        Relation::morphMap([1 => 'App\User']);

        $this->assertEquals([
            1 => 'App\User',
        ], Relation::morphMap());

        Relation::morphMap([], false);
    }

    public function testGetMorphedModel()
    {
        Relation::morphMap(['user' => 'App\User', 1 => 'App\Team']);

        $this->assertSame('App\User', Relation::getMorphedModel('user'));
        $this->assertSame('App\Team', Relation::getMorphedModel(1));
        $this->assertNull(Relation::getMorphedModel('does_not_exist'));
        $this->assertNull(Relation::getMorphedModel(null));

        Relation::morphMap([], false);
    }

    public function testGetMorphAlias()
    {
        Relation::morphMap(['user' => 'App\User']);

        $this->assertSame('user', Relation::getMorphAlias('App\User'));
        $this->assertSame('Does\Not\Exist', Relation::getMorphAlias('Does\Not\Exist'));
    }

    public function testWithoutRelations()
    {
        $original = new EloquentNoTouchingModelStub;

        $original->setRelation('foo', 'baz');

        $this->assertSame('baz', $original->getRelation('foo'));

        $model = $original->withoutRelations();

        $this->assertInstanceOf(EloquentNoTouchingModelStub::class, $model);
        $this->assertTrue($original->relationLoaded('foo'));
        $this->assertFalse($model->relationLoaded('foo'));

        $model = $original->unsetRelations();

        $this->assertInstanceOf(EloquentNoTouchingModelStub::class, $model);
        $this->assertFalse($original->relationLoaded('foo'));
        $this->assertFalse($model->relationLoaded('foo'));
    }

    public function testWithoutRelation()
    {
        $original = new EloquentNoTouchingModelStub;

        $original->setRelation('foo', 'baz');
        $original->setRelation('bar', 'qux');

        $model = $original->withoutRelation('foo');

        $this->assertInstanceOf(EloquentNoTouchingModelStub::class, $model);
        $this->assertNotSame($model, $original);
        $this->assertTrue($original->relationLoaded('foo'));
        $this->assertTrue($original->relationLoaded('bar'));
        $this->assertFalse($model->relationLoaded('foo'));
        $this->assertTrue($model->relationLoaded('bar'));
    }

    public function testWithoutRelationWithArray()
    {
        $original = new EloquentNoTouchingModelStub;

        $original->setRelation('foo', 'baz');
        $original->setRelation('bar', 'qux');
        $original->setRelation('bam', 'zap');

        $model = $original->withoutRelation(['foo', 'bar']);

        $this->assertTrue($original->relationLoaded('foo'));
        $this->assertTrue($original->relationLoaded('bar'));
        $this->assertTrue($original->relationLoaded('bam'));
        $this->assertFalse($model->relationLoaded('foo'));
        $this->assertFalse($model->relationLoaded('bar'));
        $this->assertTrue($model->relationLoaded('bam'));
    }

    public function testMacroable()
    {
        Relation::macro('foo', function () {
            return 'foo';
        });

        $model = new EloquentRelationResetModelStub;
        $relation = new EloquentRelationStub($model->newQuery(), $model);

        $result = $relation->foo();
        $this->assertSame('foo', $result);
    }

    public function testIsRelationIgnoresAttribute()
    {
        $model = new EloquentRelationAndAttributeModelStub;

        $this->assertTrue($model->isRelation('parent'));
        $this->assertFalse($model->isRelation('field'));
    }
}

class EloquentRelationResetModelStub extends Model
{
    protected $table = 'reset';

    // Override method call which would normally go through __call()

    public function getQuery()
    {
        return $this->newQuery()->getQuery();
    }
}

class EloquentRelationStub extends Relation
{
    public function addConstraints()
    {
        //
    }

    public function addEagerConstraints(array $models)
    {
        //
    }

    public function initRelation(array $models, $relation)
    {
        //
    }

    public function match(array $models, Collection $results, $relation)
    {
        //
    }

    public function getResults()
    {
        //
    }
}

class EloquentNoTouchingModelStub extends Model
{
    protected $table = 'table';
    protected $attributes = [
        'id' => 1,
    ];
}

class EloquentNoTouchingChildModelStub extends EloquentNoTouchingModelStub
{
    //
}

class EloquentNoTouchingAnotherModelStub extends Model
{
    protected $table = 'another_table';
    protected $attributes = [
        'id' => 2,
    ];
}

class EloquentRelationAndAttributeModelStub extends Model
{
    protected $table = 'one_more_table';

    public function field(): Attribute
    {
        return new Attribute(
            function ($value) {
                return $value;
            },
            function ($value) {
                return $value;
            },
        );
    }

    public function parent()
    {
        return $this->belongsTo(self::class);
    }
}
