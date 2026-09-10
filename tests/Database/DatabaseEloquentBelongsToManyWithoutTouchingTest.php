<?php

declare(strict_types=1);

namespace Illuminate\Tests\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;

class DatabaseEloquentBelongsToManyWithoutTouchingTest extends TestCase
{
    public function testItWillNotTouchRelatedModelsWhenUpdatingChild(): void
    {
        /** @var Article $related */
        $related = Double::for(Article::class)->passthru();
        $related->expects('getUpdatedAtColumn')->never();
        $related->expects('freshTimestampString')->never();

        $this->assertFalse($related::isIgnoringTouch());

        Model::withoutTouching(function () use ($related) {
            $this->assertTrue($related::isIgnoringTouch());

            $builder = Double::for(Builder::class);
            $builder->expects('join');
            $parent = Double::for(User::class);

            $parent->expects('getAttribute')->with('id')->returns(1);
            $builder->expects('getModel')->returns($related);
            $builder->expects('where');
            $grammar = Double::for(Grammar::class);
            $grammar->allows('isExpression')->returns(false);

            $queryBuilder = Double::for(QueryBuilder::class);
            $queryBuilder->allows('getGrammar')->returns($grammar);

            $builder->expects('getQuery')->times(2)->returns($queryBuilder);
            $relation = new BelongsToMany($builder, $parent, 'article_users', 'user_id', 'article_id', 'id', 'id');
            $builder->expects('update')->never();

            $relation->touch();
        });

        $this->assertFalse($related::isIgnoringTouch());
    }
}

class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['id', 'email'];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_user', 'user_id', 'article_id');
    }
}

class Article extends Model
{
    protected $table = 'articles';
    protected $fillable = ['id', 'title'];
    protected $touches = ['user'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_user', 'article_id', 'user_id');
    }
}
