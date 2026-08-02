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
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('find')->once()->with(1)->andReturn(['id' => 1, 'name' => 'Dayle']);
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
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('find')->once()->with(1)->andReturn(null);
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
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('find')->once()->with(1)->andReturn($mockUser);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertEquals(new GenericUser((array) $mockUser), $user);
    }

    public function testRetrieveTokenWithBadIdentifierReturnsNull()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('find')->once()->with(1)->andReturn(null);
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
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('find')->once()->with(1)->andReturn($mockUser);
        $hasher = TestDouble::for(Hasher::class);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertNull($user);
    }

    public function testRetrieveByCredentialsReturnsUserWhenUserIsFound()
    {
        $conn = TestDouble::for(Connection::class);
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('where')->once()->with('username', 'dayle');
        $conn->shouldReceive('whereIn')->once()->with('group', ['one', 'two']);
        $conn->shouldReceive('first')->once()->andReturn(['id' => 1, 'name' => 'taylor']);
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
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('where')->once()->with('username', 'dayle');
        $conn->shouldReceive('whereIn')->once()->with('group', ['one', 'two']);
        $conn->shouldReceive('first')->once()->andReturn(['id' => 1, 'name' => 'taylor']);
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
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($conn);
        $conn->shouldReceive('where')->once()->with('username', 'dayle');
        $conn->shouldReceive('first')->once()->andReturn(null);
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
        $hasher->shouldReceive('check')->once()->with('plain', 'hash')->andReturn(true);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->shouldReceive('getAuthPassword')->once()->andReturn('hash');
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertTrue($result);
    }

    public function testCredentialValidationFails()
    {
        $conn = TestDouble::for(Connection::class);
        $hasher = TestDouble::for(Hasher::class);
        $hasher->shouldReceive('check')->once()->with('plain', 'hash')->andReturn(false);
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->shouldReceive('getAuthPassword')->once()->andReturn('hash');
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertFalse($result);
    }

    public function testCredentialValidationFailsGracefullyWithNullPassword()
    {
        $conn = TestDouble::for(Connection::class);
        $hasher = TestDouble::for(Hasher::class);
        $hasher->shouldReceive('check')->never();
        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->shouldReceive('getAuthPassword')->once()->andReturn(null);
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertFalse($result);
    }

    public function testRehashPasswordIfRequired()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->shouldReceive('needsRehash')->once()->with('hash')->andReturn(true);
        $hasher->shouldReceive('make')->once()->with('plain')->andReturn('rehashed');

        $conn = TestDouble::for(Connection::class);
        $table = TestDouble::for(ConnectionInterface::class);
        $conn->shouldReceive('table')->once()->with('foo')->andReturn($table);
        $table->shouldReceive('where')->once()->with('id', 1)->andReturnSelf();
        $table->shouldReceive('update')->once()->with(['password_attribute' => 'rehashed']);

        $user = TestDouble::for(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifierName')->once()->andReturn('id');
        $user->shouldReceive('getAuthIdentifier')->once()->andReturn(1);
        $user->shouldReceive('getAuthPassword')->once()->andReturn('hash');
        $user->shouldReceive('getAuthPasswordName')->once()->andReturn('password_attribute');

        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);
    }

    public function testDontRehashPasswordIfNotRequired()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->shouldReceive('needsRehash')->once()->with('hash')->andReturn(false);
        $hasher->shouldNotReceive('make');

        $conn = TestDouble::for(Connection::class);
        $table = TestDouble::for(ConnectionInterface::class);
        $conn->shouldNotReceive('table');
        $table->shouldNotReceive('where');
        $table->shouldNotReceive('update');

        $user = TestDouble::for(Authenticatable::class);
        $user->shouldReceive('getAuthPassword')->once()->andReturn('hash');
        $user->shouldNotReceive('getAuthIdentifierName');
        $user->shouldNotReceive('getAuthIdentifier');
        $user->shouldNotReceive('getAuthPasswordName');

        $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
        $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);
    }
}
