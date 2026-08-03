<?php

namespace Illuminate\Tests\Auth;

use JMac\Testing\TestDouble;
use Illuminate\Auth\DatabaseUserProvider;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

class AuthDatabaseUserProviderTest extends TestCase
{
    public function testRetrieveByIDReturnsUserWhenUserIsFound()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('find')->with(1)->returns(['id' => 1, 'name' => 'Dayle']);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveById(1);

        $this->assertInstanceOf(GenericUser::class, $user);
        $this->assertSame(1, $user->getAuthIdentifier());
        $this->assertSame('Dayle', $user->name);
    }

    public function testRetrieveByIDReturnsNullWhenUserIsNotFound()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('find')->with(1)->returns(null);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveById(1);

        $this->assertNull($user);
    }

    public function testRetrieveByTokenReturnsUser()
    {
        $mockUser = new stdClass;
        $mockUser->remember_token = 'a';

        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('find')->with(1)->returns($mockUser);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertEquals(new GenericUser((array) $mockUser), $user);
    }

    public function testRetrieveTokenWithBadIdentifierReturnsNull()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('find')->with(1)->returns(null);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertNull($user);
    }

    public function testRetrieveByBadTokenReturnsNull()
    {
        $mockUser = new stdClass;
        $mockUser->remember_token = null;

        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('find')->with(1)->returns($mockUser);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertNull($user);
    }

    public function testRetrieveByCredentialsReturnsUserWhenUserIsFound()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('where')->with('username', 'dayle');
        $conn->expects('whereIn')->with('group', ['one', 'two']);
        $conn->expects('first')->returns(['id' => 1, 'name' => 'taylor']);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByCredentials(['username' => 'dayle', 'password' => 'foo', 'group' => ['one', 'two']]);

        $this->assertInstanceOf(GenericUser::class, $user);
        $this->assertSame(1, $user->getAuthIdentifier());
        $this->assertSame('taylor', $user->name);
    }

    public function testRetrieveByCredentialsAcceptsCallback()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('where')->with('username', 'dayle');
        $conn->expects('whereIn')->with('group', ['one', 'two']);
        $conn->expects('first')->returns(['id' => 1, 'name' => 'taylor']);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');

        $user = $provider->retrieveByCredentials([function ($builder) {
            $builder->where('username', 'dayle');
            $builder->whereIn('group', ['one', 'two']);
        }]);

        $this->assertInstanceOf(GenericUser::class, $user);
        $this->assertSame(1, $user->getAuthIdentifier());
        $this->assertSame('taylor', $user->name);
    }

    public function testRetrieveByCredentialsReturnsNullWhenUserIsFound()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->expects('table')->with('foo')->returns($conn);
        $conn->expects('where')->with('username', 'dayle');
        $conn->expects('first')->returns(null);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByCredentials(['username' => 'dayle']);

        $this->assertNull($user);
    }

    public function testRetrieveByCredentialsWithMultiplyPasswordsReturnsNull()
    {
        $conn = TestDouble::for(Connection::class);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByCredentials([
            'password' => 'dayle',
            'password2' => 'night',
        ]);

        $this->assertNull($user);
    }

    public function testCredentialValidation()
    {
        $conn = TestDouble::for(Connection::class);
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('check')->with('plain', 'hash')->returns(true);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns('hash');
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertTrue($result);
    }

    public function testCredentialValidationFails()
    {
        $conn = TestDouble::for(Connection::class);
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('check')->with('plain', 'hash')->returns(false);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns('hash');
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertFalse($result);
    }

    public function testCredentialValidationFailsGracefullyWithNullPassword()
    {
        $conn = TestDouble::for(Connection::class);
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('check')->never();
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns(null);
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertFalse($result);
    }

    public function testRehashPasswordIfRequired()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('needsRehash')->with('hash')->returns(true);
        $hasher->expects('make')->with('plain')->returns('rehashed');

        $conn = TestDouble::for(Connection::class);
        $table = TestDouble::for(ConnectionInterface::class);
        $conn->expects('table')->with('foo')->returns($table);
        $table->expects('where')->with('id', 1)->returns($table);
        $table->expects('update')->with(['password_attribute' => 'rehashed']);

        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthIdentifierName')->returns('id');
        $user->expects('getAuthIdentifier')->returns(1);
        $user->expects('getAuthPassword')->returns('hash');
        $user->expects('getAuthPasswordName')->returns('password_attribute');

        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);
    }

    public function testDontRehashPasswordIfNotRequired()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('needsRehash')->with('hash')->returns(false);
        $hasher->shouldNotReceive('make');

        $conn = TestDouble::for(Connection::class);
        $table = TestDouble::for(ConnectionInterface::class);
        $conn->shouldNotReceive('table');
        $table->shouldNotReceive('where');
        $table->shouldNotReceive('update');

        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns('hash');
        $user->shouldNotReceive('getAuthIdentifierName');
        $user->shouldNotReceive('getAuthIdentifier');
        $user->shouldNotReceive('getAuthPasswordName');

        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);
    }
}
