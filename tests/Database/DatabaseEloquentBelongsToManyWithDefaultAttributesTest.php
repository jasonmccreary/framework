<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Grammars\Grammar;
use PHPUnit\Framework\TestCase;
use stdClass;

class DatabaseEloquentBelongsToManyWithDefaultAttributesTest extends TestCase
{
    public function testWithPivotValueMethodSetsWhereConditionsForFetching()
    {
        $relation = $this->getMockBuilder(BelongsToMany::class)->onlyMethods(['touchIfTouching'])->setConstructorArgs($this->getRelationArguments())->getMock();
        $relation->withPivotValue(['is_admin' => 1]);
    }

    public function testWithPivotValueMethodSetsDefaultArgumentsForInsertion()
    {
        $relation = $this->getMockBuilder(BelongsToMany::class)->onlyMethods(['touchIfTouching'])->setConstructorArgs($this->getRelationArguments())->getMock();
        $relation->withPivotValue(['is_admin' => 1]);

        $query = TestDouble::for(stdClass::class);
        $query->expects('from')->with('club_user')->returns($query);
        $query->expects('insert')->with([['club_id' => 1, 'user_id' => 1, 'is_admin' => 1]])->returns(true);
        $relation->getQuery()->getQuery()->expects('newQuery')->returns($query);

        $relation->attach(1);
    }

    public function getRelationArguments()
    {
        $parent = TestDouble::for(Model::class);
        $parent->allows('getKey')->returns(1);
        $parent->allows('getCreatedAtColumn')->returns('created_at');
        $parent->allows('getUpdatedAtColumn')->returns('updated_at');
        $parent->allows('getAttribute')->with('id')->returns(1);

        $builder = TestDouble::for(Builder::class);
        $related = TestDouble::for(Model::class);
        $builder->allows('getModel')->returns($related);

        $related->allows('getTable')->returns('users');
        $related->allows('getKeyName')->returns('id');
        $related->allows('qualifyColumn')->with('id')->returns('users.id');

        $builder->expects('join')->with('club_user', 'users.id', '=', 'club_user.user_id');
        $builder->expects('where')->with('club_user.club_id', '=', 1);
        $builder->expects('where')->with('club_user.is_admin', '=', 1, 'and');

        $builder->allows('getQuery')->returns($mockQueryBuilder = TestDouble::for(stdClass::class));
        $mockQueryBuilder->allows('getGrammar')->returns(m::mock(Grammar::class, ['isExpression' => false]));

        return [$builder, $parent, 'club_user', 'club_id', 'user_id', 'id', 'id', null, false];
    }
}
