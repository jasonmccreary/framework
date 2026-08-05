<?php

namespace Illuminate\Tests\Auth;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use stdClass;

class AuthDatabaseTokenRepositoryTest extends TestCase
{
    public function testCreateInsertsNewRecordIntoTable()
    {
        $repo = $this->getRepo();
        $repo->getHasher()->expects('make')->returns('hashed-token');
        $repo->getConnection()->expects('table')->times(2)->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('delete');
        $query->expects('insert');
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->times(2)->returns('email');

        $results = $repo->create($user);

        $this->assertIsString($results);
        $this->assertGreaterThan(1, strlen($results));
    }

    public function testExistReturnsFalseIfNoRowFoundForUser()
    {
        $repo = $this->getRepo();
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('first')->returns(null);
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->exists($user, 'token'));
    }

    public function testExistReturnsFalseIfRecordIsExpired()
    {
        $repo = $this->getRepo();
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = Carbon::now()->subSeconds(300000)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->exists($user, 'token'));
    }

    public function testExistReturnsTrueIfValidRecordExists()
    {
        $repo = $this->getRepo();
        $repo->getHasher()->expects('check')->with('token', 'hashed-token')->returns(true);
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = Carbon::now()->subMinutes(10)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertTrue($repo->exists($user, 'token'));
    }

    public function testExistReturnsFalseIfInvalidToken()
    {
        $repo = $this->getRepo();
        $repo->getHasher()->expects('check')->with('wrong-token', 'hashed-token')->returns(false);
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = Carbon::now()->subMinutes(10)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->exists($user, 'wrong-token'));
    }

    public function testRecentlyCreatedReturnsFalseIfNoRowFoundForUser()
    {
        $repo = $this->getRepo();
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('first')->returns(null);
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->recentlyCreatedToken($user));
    }

    public function testRecentlyCreatedReturnsTrueIfRecordIsRecentlyCreated()
    {
        Carbon::setTestNow($now = Carbon::now());

        $repo = $this->getRepo();
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = $now->subSeconds(59)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertTrue($repo->recentlyCreatedToken($user));
    }

    public function testRecentlyCreatedReturnsFalseIfValidRecordExists()
    {
        Carbon::setTestNow($now = Carbon::now());

        $repo = $this->getRepo();
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $date = $now->subSeconds(61)->toDateTimeString();
        $query->expects('first')->returns((object) ['created_at' => $date, 'token' => 'hashed-token']);
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $this->assertFalse($repo->recentlyCreatedToken($user));
    }

    public function testDeleteMethodDeletesByToken()
    {
        $repo = $this->getRepo();
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('email', 'email')->returns($query);
        $query->expects('delete');
        $user = TestDouble::for(CanResetPassword::class);
        $user->expects('getEmailForPasswordReset')->returns('email');

        $repo->delete($user);
    }

    public function testDeleteExpiredMethodDeletesExpiredTokens()
    {
        $repo = $this->getRepo();
        $repo->getConnection()->expects('table')->with('table')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('created_at', '<', Argument::any())->returns($query);
        $query->expects('delete');

        $repo->deleteExpired();
    }

    protected function getRepo()
    {
        return new DatabaseTokenRepository(
            TestDouble::for(Connection::class),
            TestDouble::for(Hasher::class),
            'table', 'key');
    }
}
