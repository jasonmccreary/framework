<?php

namespace Illuminate\Tests\Auth;

use JMac\Testing\TestDouble;
use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class AuthTokenGuardTest extends TestCase
{
    public function testUserCanBeRetrievedByQueryStringVariable()
    {
        $provider = TestDouble::for(UserProvider::class);
        $user = new AuthTokenGuardTestUser;
        $user->id = 1;
        $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->returns($user);
        $request = Request::create('/', 'GET', ['api_token' => 'foo']);

        $guard = new TokenGuard($provider, $request);

        $user = $guard->user();

        $this->assertSame(1, $user->id);
        $this->assertTrue($guard->check());
        $this->assertFalse($guard->guest());
        $this->assertSame(1, $guard->id());
    }

    public function testTokenCanBeHashed()
    {
        $provider = TestDouble::for(UserProvider::class);
        $user = new AuthTokenGuardTestUser;
        $user->id = 1;
        $provider->expects('retrieveByCredentials')->with(['api_token' => hash('sha256', 'foo')])->returns($user);
        $request = Request::create('/', 'GET', ['api_token' => 'foo']);

        $guard = new TokenGuard($provider, $request, 'api_token', 'api_token', $hash = true);

        $user = $guard->user();

        $this->assertSame(1, $user->id);
        $this->assertTrue($guard->check());
        $this->assertFalse($guard->guest());
        $this->assertSame(1, $guard->id());
    }

    public function testUserCanBeRetrievedByAuthHeaders()
    {
        $provider = TestDouble::for(UserProvider::class);
        $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->returns((object) ['id' => 1]);
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'foo']);

        $guard = new TokenGuard($provider, $request);

        $user = $guard->user();

        $this->assertSame(1, $user->id);
    }

    public function testUserCanBeRetrievedByBearerToken()
    {
        $provider = TestDouble::for(UserProvider::class);
        $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->returns((object) ['id' => 1]);
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer foo']);

        $guard = new TokenGuard($provider, $request);

        $user = $guard->user();

        $this->assertSame(1, $user->id);
    }

    public function testValidateCanDetermineIfCredentialsAreValid()
    {
        $provider = TestDouble::for(UserProvider::class);
        $user = new AuthTokenGuardTestUser;
        $user->id = 1;
        $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->returns($user);
        $request = Request::create('/', 'GET', ['api_token' => 'foo']);

        $guard = new TokenGuard($provider, $request);

        $this->assertTrue($guard->validate(['api_token' => 'foo']));
    }

    public function testValidateCanDetermineIfCredentialsAreInvalid()
    {
        $provider = TestDouble::for(UserProvider::class);
        $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->returns(null);
        $request = Request::create('/', 'GET', ['api_token' => 'foo']);

        $guard = new TokenGuard($provider, $request);

        $this->assertFalse($guard->validate(['api_token' => 'foo']));
    }

    public function testValidateIfApiTokenIsEmpty()
    {
        $provider = TestDouble::for(UserProvider::class);
        $request = Request::create('/', 'GET', ['api_token' => '']);

        $guard = new TokenGuard($provider, $request);

        $this->assertFalse($guard->validate(['api_token' => '']));
    }

    public function testItAllowsToPassCustomRequestInSetterAndUseItForValidation()
    {
        $provider = TestDouble::for(UserProvider::class);
        $user = new AuthTokenGuardTestUser;
        $user->id = 1;
        $provider->expects('retrieveByCredentials')->with(['api_token' => 'custom'])->returns($user);
        $request = Request::create('/', 'GET', ['api_token' => 'foo']);

        $guard = new TokenGuard($provider, $request);
        $guard->setRequest(Request::create('/', 'GET', ['api_token' => 'custom']));

        $user = $guard->user();

        $this->assertSame(1, $user->id);
    }

    public function testUserCanBeRetrievedByBearerTokenWithCustomKey()
    {
        $provider = TestDouble::for(UserProvider::class);
        $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->returns((object) ['id' => 1]);
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer foo']);

        $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

        $user = $guard->user();

        $this->assertSame(1, $user->id);
    }

    public function testUserCanBeRetrievedByQueryStringVariableWithCustomKey()
    {
        $provider = TestDouble::for(UserProvider::class);
        $user = new AuthTokenGuardTestUser;
        $user->id = 1;
        $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->returns($user);
        $request = Request::create('/', 'GET', ['custom_token_field' => 'foo']);

        $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

        $user = $guard->user();

        $this->assertSame(1, $user->id);
        $this->assertTrue($guard->check());
        $this->assertFalse($guard->guest());
        $this->assertSame(1, $guard->id());
    }

    public function testUserCanBeRetrievedByAuthHeadersWithCustomField()
    {
        $provider = TestDouble::for(UserProvider::class);
        $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->returns((object) ['id' => 1]);
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'foo']);

        $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

        $user = $guard->user();

        $this->assertSame(1, $user->id);
    }

    public function testValidateCanDetermineIfCredentialsAreValidWithCustomKey()
    {
        $provider = TestDouble::for(UserProvider::class);
        $user = new AuthTokenGuardTestUser;
        $user->id = 1;
        $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->returns($user);
        $request = Request::create('/', 'GET', ['custom_token_field' => 'foo']);

        $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

        $this->assertTrue($guard->validate(['custom_token_field' => 'foo']));
    }

    public function testValidateCanDetermineIfCredentialsAreInvalidWithCustomKey()
    {
        $provider = TestDouble::for(UserProvider::class);
        $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->returns(null);
        $request = Request::create('/', 'GET', ['custom_token_field' => 'foo']);

        $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

        $this->assertFalse($guard->validate(['custom_token_field' => 'foo']));
    }

    public function testValidateIfApiTokenIsEmptyWithCustomKey()
    {
        $provider = TestDouble::for(UserProvider::class);
        $request = Request::create('/', 'GET', ['custom_token_field' => '']);

        $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

        $this->assertFalse($guard->validate(['custom_token_field' => '']));
    }
}

class AuthTokenGuardTestUser
{
    public $id;

    public function getAuthIdentifier()
    {
        return $this->id;
    }
}
