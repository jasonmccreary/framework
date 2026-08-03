<?php

namespace Illuminate\Tests\Foundation;

use JMac\Testing\TestDouble;
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
        $guard = TestDouble::for(Guard::class);

        $auth = TestDouble::for(AuthManager::class);
        $auth->expects('guard')->returns($guard);

        $this->app = TestDouble::for(Application::class);
        $this->app->shouldReceive('make')
            ->once()
            ->withArgs(['auth'])
            ->andReturn($auth);

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
        $expected = TestDouble::for(Authenticatable::class);
        $expected->allows('getAuthIdentifier')->returns('1');

        $this->mockGuard()->expects('user')->returns($expected);

        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getAuthIdentifier')->returns('1');

        $this->assertAuthenticatedAs($user);
    }

    protected function setupProvider(array $credentials)
    {
        $user = TestDouble::for(Authenticatable::class);

        $provider = TestDouble::for(UserProvider::class);

        $provider->allows('retrieveByCredentials')->with($credentials)->returns($user);

        $provider->allows('validateCredentials')->with($user, $credentials)->returns($this->credentials === $credentials);

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
