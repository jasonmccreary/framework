<?php

namespace Illuminate\Tests\Foundation;

use JMac\Testing\Double;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use PHPUnit\Framework\TestCase;

class FoundationAuthenticationTest extends TestCase
{
    use InteractsWithAuthentication;

    /**
     * @var \Mockery
     */
    protected $app;

    /**
     * @var array
     */
    protected $credentials = [
        'email' => 'someone@laravel.com',
        'password' => 'secret_password',
    ];

    /**
     * @return \Illuminate\Contracts\Auth\Guard|\Mockery\LegacyMockInterface|\Mockery\MockInterface
     */
    protected function mockGuard()
    {
        $guard = Double::for(Guard::class);

        $auth = Double::for(AuthManager::class);
        $auth->expects('guard')->returns($guard);

        $this->app = Double::for(Application::class);
        $this->app->expects('make')->with('auth')->returns($auth);

        return $guard;
    }

    public function testAssertAuthenticated()
    {
        $this->mockGuard()->expects('check')->returns(true);

        $this->assertAuthenticated();
    }

    public function testAssertGuest()
    {
        $this->mockGuard()->expects('check')->returns(false);

        $this->assertGuest();
    }

    public function testAssertAuthenticatedAs()
    {
        $expected = Double::for(Authenticatable::class);
        $expected->expects('getAuthIdentifier')->returns('1');

        $this->mockGuard()->expects('user')->returns($expected);

        $user = Double::for(Authenticatable::class);
        $user->expects('getAuthIdentifier')->returns('1');

        $this->assertAuthenticatedAs($user);
    }

    protected function setupProvider(array $credentials)
    {
        $user = Double::for(Authenticatable::class);

        $provider = Double::for(UserProvider::class);

        $provider->expects('retrieveByCredentials')->with($credentials)->returns($user);

        $provider->expects('validateCredentials')->with($user, $credentials)->returns($this->credentials === $credentials);

        $this->mockGuard()->expects('getProvider')->returns($provider);
    }

    public function testAssertCredentials()
    {
        $this->setupProvider($this->credentials);

        $this->assertCredentials($this->credentials);
    }

    public function testAssertCredentialsMissing()
    {
        $credentials = [
            'email' => 'invalid',
            'password' => 'credentials',
        ];

        $this->setupProvider($credentials);

        $this->assertInvalidCredentials($credentials);
    }
}
