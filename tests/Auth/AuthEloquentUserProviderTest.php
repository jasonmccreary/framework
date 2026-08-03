<?php

namespace Illuminate\Tests\Auth;

use JMac\Testing\TestDouble;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use PHPUnit\Framework\TestCase;
use stdClass;

class AuthEloquentUserProviderTest extends TestCase
{
    public function testRetrieveByIDReturnsUser()
    {
        $provider = $this->getProviderMock();
        $mock = TestDouble::for(stdClass::class);
        $mock->expects('newQuery')->returns($mock);
        $mock->expects('getAuthIdentifierName')->returns('id');
        $mock->expects('where')->with('id', 1)->returns($mock);
        $mock->expects('first')->returns('bar');
        $provider->expects($this->once())->method('createModel')->willReturn($mock);
        $user = $provider->retrieveById(1);

        $this->assertSame('bar', $user);
    }

    public function testRetrieveByTokenReturnsUser()
    {
        $mockUser = TestDouble::for(stdClass::class);
        $mockUser->expects('getRememberToken')->returns('a');

        $provider = $this->getProviderMock();
        $mock = TestDouble::for(stdClass::class);
        $mock->expects('newQuery')->returns($mock);
        $mock->expects('getAuthIdentifierName')->returns('id');
        $mock->expects('where')->with('id', 1)->returns($mock);
        $mock->expects('first')->returns($mockUser);
        $provider->expects($this->once())->method('createModel')->willReturn($mock);
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertEquals($mockUser, $user);
    }

    public function testRetrieveTokenWithBadIdentifierReturnsNull()
    {
        $provider = $this->getProviderMock();
        $mock = TestDouble::for(stdClass::class);
        $mock->expects('newQuery')->returns($mock);
        $mock->expects('getAuthIdentifierName')->returns('id');
        $mock->expects('where')->with('id', 1)->returns($mock);
        $mock->expects('first')->returns(null);
        $provider->expects($this->once())->method('createModel')->willReturn($mock);
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertNull($user);
    }

    public function testRetrievingWithOnlyPasswordCredentialReturnsNull()
    {
        $provider = $this->getProviderMock();
        $user = $provider->retrieveByCredentials(['api_password' => 'foo']);

        $this->assertNull($user);
    }

    public function testRetrieveByBadTokenReturnsNull()
    {
        $mockUser = TestDouble::for(stdClass::class);
        $mockUser->expects('getRememberToken')->returns(null);

        $provider = $this->getProviderMock();
        $mock = TestDouble::for(stdClass::class);
        $mock->expects('newQuery')->returns($mock);
        $mock->expects('getAuthIdentifierName')->returns('id');
        $mock->expects('where')->with('id', 1)->returns($mock);
        $mock->expects('first')->returns($mockUser);
        $provider->expects($this->once())->method('createModel')->willReturn($mock);
        $user = $provider->retrieveByToken(1, 'a');

        $this->assertNull($user);
    }

    public function testRetrieveByCredentialsReturnsUser()
    {
        $provider = $this->getProviderMock();
        $mock = TestDouble::for(stdClass::class);
        $mock->expects('newQuery')->returns($mock);
        $mock->expects('where')->with('username', 'dayle');
        $mock->expects('whereIn')->with('group', ['one', 'two']);
        $mock->expects('first')->returns('bar');
        $provider->expects($this->once())->method('createModel')->willReturn($mock);
        $user = $provider->retrieveByCredentials(['username' => 'dayle', 'password' => 'foo', 'group' => ['one', 'two']]);

        $this->assertSame('bar', $user);
    }

    public function testRetrieveByCredentialsAcceptsCallback()
    {
        $provider = $this->getProviderMock();
        $mock = TestDouble::for(stdClass::class);
        $mock->expects('newQuery')->returns($mock);
        $mock->expects('where')->with('username', 'dayle');
        $mock->expects('whereIn')->with('group', ['one', 'two']);
        $mock->expects('first')->returns('bar');
        $provider->expects($this->once())->method('createModel')->willReturn($mock);
        $user = $provider->retrieveByCredentials([function ($builder) {
            $builder->where('username', 'dayle');
            $builder->whereIn('group', ['one', 'two']);
        }]);

        $this->assertSame('bar', $user);
    }

    public function testRetrieveByCredentialsWithMultiplyPasswordsReturnsNull()
    {
        $provider = $this->getProviderMock();
        $user = $provider->retrieveByCredentials([
            'password' => 'dayle',
            'password2' => 'night',
        ]);

        $this->assertNull($user);
    }

    public function testCredentialValidation()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('check')->with('plain', 'hash')->returns(true);
        $provider = new EloquentUserProvider($hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns('hash');
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertTrue($result);
    }

    public function testCredentialValidationFailed()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('check')->with('plain', 'hash')->returns(false);
        $provider = new EloquentUserProvider($hasher, 'foo');
        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns('hash');
        $result = $provider->validateCredentials($user, ['password' => 'plain']);

        $this->assertFalse($result);
    }

    public function testCredentialValidationFailsGracefullyWithNullPassword()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('check')->never();
        $provider = new EloquentUserProvider($hasher, 'foo');
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

        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns('hash');
        $user->expects('getAuthPasswordName')->returns('password_attribute');
        $user->expects('forceFill')->with(['password_attribute' => 'rehashed'])->returns($user);
        $user->expects('save');

        $provider = new EloquentUserProvider($hasher, 'foo');
        $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);
    }

    public function testDontRehashPasswordIfNotRequired()
    {
        $hasher = TestDouble::for(Hasher::class);
        $hasher->expects('needsRehash')->with('hash')->returns(false);
        $hasher->shouldNotReceive('make');

        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getAuthPassword')->returns('hash');
        $user->shouldNotReceive('getAuthPasswordName');
        $user->shouldNotReceive('forceFill');
        $user->shouldNotReceive('save');

        $provider = new EloquentUserProvider($hasher, 'foo');
        $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);
    }

    public function testModelsCanBeCreated()
    {
        $hasher = TestDouble::for(Hasher::class);
        $provider = new EloquentUserProvider($hasher, EloquentProviderUserStub::class);
        $model = $provider->createModel();

        $this->assertInstanceOf(EloquentProviderUserStub::class, $model);
    }

    public function testRegistersQueryHandler()
    {
        $callback = function ($builder) {
            $builder->whereIn('group', ['one', 'two']);
        };

        $provider = $this->getProviderMock();
        $mock = TestDouble::for(stdClass::class);
        $mock->expects('newQuery')->returns($mock);
        $mock->expects('where')->with('username', 'dayle');
        $mock->expects('whereIn')->with('group', ['one', 'two']);
        $mock->expects('first')->returns('bar');
        $provider->expects($this->once())->method('createModel')->willReturn($mock);
        $provider->withQuery($callback);
        $user = $provider->retrieveByCredentials([function ($builder) {
            $builder->where('username', 'dayle');
        }]);

        $this->assertSame('bar', $user);
        $this->assertSame($callback, $provider->getQueryCallback());
    }

    protected function getProviderMock()
    {
        $hasher = TestDouble::for(Hasher::class);

        return $this->getMockBuilder(EloquentUserProvider::class)->onlyMethods(['createModel'])->setConstructorArgs([$hasher, 'foo'])->getMock();
    }
}

class EloquentProviderUserStub
{
    //
}
