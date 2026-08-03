<?php

declare(strict_types=1);

namespace Illuminate\Tests\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Grammars\Grammar;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;
use stdClass;

class DatabaseEloquentBelongsToManyWithoutTouchingTest extends TestCase
{
    public function testItWillNotTouchRelatedModelsWhenUpdatingChild(): void
    {
        /** @var Article $related */
        $related = TestDouble::for(Article::class)->passthru();
        $related->expects('getUpdatedAtColumn')->never();
        $related->expects('freshTimestampString')->never();

        $this->assertFalse($related::isIgnoringTouch());

        Model::withoutTouching(function () use ($related) {
            $this->assertTrue($related::isIgnoringTouch());

            $builder = TestDouble::for(Builder::class);
            $builder->allows('join');
            $parent = TestDouble::for(User::class);

            $parent->allows('getAttribute')->with('id')->returns(1);
            $builder->allows('getModel')->returns($related);
            $builder->allows('where');

            $grammar = TestDouble::for(Grammar::class);
            $grammar->allows('isExpression')->returns(false);

            $query = TestDouble::for(stdClass::class);
            $query->allows('getGrammar')->returns($grammar);

            $builder->allows('getQuery')->returns($query);
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
