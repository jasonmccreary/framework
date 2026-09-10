<?php

namespace Illuminate\Tests\Auth;

use JMac\Testing\Double;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthDatabaseTokenRepositoryTest extends TestCase
{
    public function testCreateInsertsNewRecordIntoTable()
    {
        $repo = $this->getRepo();
        $repo->getHasher()->expects('make')->returns('hashed-token');
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->times(2)->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('delete');
        $query->expects('insert');
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->times(2)->returns('email');

        $results = $repo->create($user);

        $this->assertIsString($results);
        $this->assertGreaterThan(1, strlen($results));
    }

    public function testExistReturnsFalseIfNoRowFoundForUser()
    {
        $repo = $this->getRepo();
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('first')->returns(null);
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->exists($user, 'token'));
    }

    public function testExistReturnsFalseIfRecordIsExpired()
    {
        $repo = $this->getRepo();
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = Carbon::now()->subSeconds(300000)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->exists($user, 'token'));
    }

    public function testExistReturnsTrueIfValidRecordExists()
    {
        $repo = $this->getRepo();
        $repo->getHasher()->expects('check')->with('token', 'hashed-token')->returns(true);
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = Carbon::now()->subMinutes(10)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertTrue($repo->exists($user, 'token'));
    }

    public function testExistReturnsFalseIfInvalidToken()
    {
        $repo = $this->getRepo();
        $repo->getHasher()->expects('check')->with('wrong-token', 'hashed-token')->returns(false);
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = Carbon::now()->subMinutes(10)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->exists($user, 'wrong-token'));
    }

    public function testRecentlyCreatedReturnsFalseIfNoRowFoundForUser()
    {
        $repo = $this->getRepo();
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('first')->returns(null);
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->recentlyCreatedToken($user));
    }

    public function testRecentlyCreatedReturnsTrueIfRecordIsRecentlyCreated()
    {
        Carbon::setTestNow($now = Carbon::now());

        $repo = $this->getRepo();
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = $now->subSeconds(59)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertTrue($repo->recentlyCreatedToken($user));
    }

    public function testRecentlyCreatedReturnsFalseIfValidRecordExists()
    {
        Carbon::setTestNow($now = Carbon::now());

        $repo = $this->getRepo();
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = $now->subSeconds(61)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->recentlyCreatedToken($user));
    }

    public function testDeleteMethodDeletesByToken()
    {
        $repo = $this->getRepo();
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('delete');
        $user = Double::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $repo->delete($user);
    }

    public function testDeleteExpiredMethodDeletesExpiredTokens()
    {
        $repo = $this->getRepo();
        $query = Double::for(Builder::class);
        $repo->getConnection()->expects('table')->with('table')->returns($query);
        $query->expects('where')->with('created_at', '<', Mockery::any())->returns($query);
        $query->expects('delete');

        $repo->deleteExpired();
    }

    protected function getRepo()
    {
        return new DatabaseTokenRepository(
            Double::for(Connection::class),
            Double::for(Hasher::class),
            'table', 'key');
    }
}
