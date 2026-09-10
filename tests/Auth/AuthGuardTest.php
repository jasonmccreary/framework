<?php

namespace Illuminate\Tests\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Validated;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Cookie\CookieJar;
use Illuminate\Support\Timebox;
use JMac\Testing\Double;
use JMac\Testing\Matching\Argument;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthGuardTest extends TestCase
{
    public function testBasicReturnsNullOnValidAttempt()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = new class('default', $provider, $session) extends SessionGuard
        {
            public function check()
            {
                return false;
            }

            public function attempt(#[\SensitiveParameter] array $credentials = [], $remember = false)
            {
                Assert::assertSame(['email' => 'foo@bar.com', 'password' => 'secret'], $credentials);

                return true;
            }
        };
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email');
    }

    public function testBasicReturnsNullWhenAlreadyLoggedIn()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = new class('default', $provider, $session) extends SessionGuard
        {
            public function check()
            {
                return true;
            }

            public function attempt(#[\SensitiveParameter] array $credentials = [], $remember = false)
            {
                Assert::fail('attempt() should not be called.');
            }
        };
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email');
    }

    public function testBasicReturnsResponseOnFailure()
    {
        $this->expectException(UnauthorizedHttpException::class);

        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = Double::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));
        $guard->expects('check')->returns(false);
        $guard->expects('attempt')->with(['email' => 'foo@bar.com', 'password' => 'secret'])->returns(false);
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);
        $guard->basic('email');
    }

    public function testBasicWithExtraConditions()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = new class('default', $provider, $session) extends SessionGuard
        {
            public function check()
            {
                return false;
            }

            public function attempt(#[\SensitiveParameter] array $credentials = [], $remember = false)
            {
                Assert::assertSame(['email' => 'foo@bar.com', 'password' => 'secret', 'active' => 1], $credentials);

                return true;
            }
        };
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email', ['active' => 1]);
    }

    public function testBasicWithExtraArrayConditions()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = new class('default', $provider, $session) extends SessionGuard
        {
            public function check()
            {
                return false;
            }

            public function attempt(#[\SensitiveParameter] array $credentials = [], $remember = false)
            {
                Assert::assertSame(['email' => 'foo@bar.com', 'password' => 'secret', 'active' => 1, 'type' => [1, 2, 3]], $credentials);

                return true;
            }
        };
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email', ['active' => 1, 'type' => [1, 2, 3]]);
    }

    public function testAttemptCallsRetrieveByCredentials()
    {
        $guard = $this->getGuard();
        $events = Double::for(Dispatcher::class);
        $guard->setDispatcher($events);
        $timebox = $guard->getTimebox();
        $timebox->expects('call')->resolves(function ($callback) use ($timebox) {
            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Failed::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class))->never();
        $guard->getProvider()->expects('retrieveByCredentials')->with(['foo']);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->never();
        $guard->attempt(['foo']);
    }

    public function testAttemptReturnsUserInterface()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $guard = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['login'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
        $events = Double::for(Dispatcher::class);
        $guard->setDispatcher($events);
        $timebox->expects('call')->resolves(function ($callback, $microseconds) use ($timebox) {
            $timebox->expects('returnEarly');

            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class));
        $user = $this->createStub(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByCredentials')->returns($user);
        $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->returns(true);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);
        $guard->expects($this->once())->method('login')->with($user);
        $this->assertTrue($guard->attempt(['foo']));
    }

    public function testAttemptReturnsFalseIfUserNotGiven()
    {
        $mock = $this->getGuard();
        $events = Double::for(Dispatcher::class);
        $mock->setDispatcher($events);
        $timebox = $mock->getTimebox();
        $timebox->expects('call')->resolves(function ($callback, $microseconds) use ($timebox) {
            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Failed::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class))->never();
        $mock->getProvider()->expects('retrieveByCredentials')->returns(null);
        $mock->getProvider()->expects('rehashPasswordIfRequired')->never();
        $this->assertFalse($mock->attempt(['foo']));
    }

    public function testAttemptAndWithCallbacks()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
        $events = Double::for(Dispatcher::class);
        $mock->setDispatcher($events);
        $timebox->allows('call')->resolves(function ($callback) use ($timebox) {
            $timebox->allows('returnEarly');

            return $callback($timebox);
        });
        $user = Double::for(Authenticatable::class);
        $events->expects('dispatch')->times(3)->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Login::class));
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $events->expects('dispatch')->times(2)->with(Argument::type(Validated::class));
        $events->expects('dispatch')->times(2)->with(Argument::type(Failed::class));
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $user->expects('getAuthIdentifier')->returns('bar');
        $mock->getSession()->expects('put')->with('foo', 'bar');
        $session->expects('regenerate');
        $mock->getProvider()->expects('retrieveByCredentials')->times(3)->with(['foo'])->returns($user);
        $mock->getProvider()->expects('validateCredentials')->returns(false);
        $mock->getProvider()->expects('validateCredentials')->times(2)->returns(true);
        $mock->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);

        $this->assertTrue($mock->attemptWhen(['foo'], function ($user, $guard) {
            $this->assertInstanceOf(Authenticatable::class, $user);
            $this->assertInstanceOf(SessionGuard::class, $guard);

            return true;
        }));

        $this->assertFalse($mock->attemptWhen(['foo'], function ($user, $guard) {
            $this->assertInstanceOf(Authenticatable::class, $user);
            $this->assertInstanceOf(SessionGuard::class, $guard);

            return false;
        }));

        $executed = false;

        $this->assertFalse($mock->attemptWhen(['foo'], false, function () use (&$executed) {
            return $executed = true;
        }));

        $this->assertFalse($executed);
    }

    public function testAttemptRehashesPasswordWhenRequired()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $guard = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['login'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
        $events = Double::for(Dispatcher::class);
        $guard->setDispatcher($events);
        $timebox->expects('call')->resolves(function ($callback, $microseconds) use ($timebox) {
            $timebox->expects('returnEarly');

            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class));
        $user = $this->createStub(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByCredentials')->returns($user);
        $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->returns(true);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);
        $guard->expects($this->once())->method('login')->with($user);
        $this->assertTrue($guard->attempt(['foo']));
    }

    public function testAttemptDoesntRehashPasswordWhenDisabled()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $guard = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['login'])
            ->setConstructorArgs(['default', $provider, $session, $request, $timebox, $rehashOnLogin = false])
            ->getMock();
        $events = Double::for(Dispatcher::class);
        $guard->setDispatcher($events);
        $timebox->expects('call')->resolves(function ($callback, $microseconds) use ($timebox) {
            $timebox->expects('returnEarly');

            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class));
        $user = $this->createStub(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByCredentials')->returns($user);
        $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->returns(true);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->never();
        $guard->expects($this->once())->method('login')->with($user);
        $this->assertTrue($guard->attempt(['foo']));
    }

    public function testLoginStoresIdentifierInSession()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $user = Double::for(Authenticatable::class);
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $user->expects('getAuthIdentifier')->returns('bar');
        $mock->getSession()->expects('put')->with('foo', 'bar');
        $session->expects('regenerate');
        $mock->login($user);
    }

    public function testSessionGuardIsMacroable()
    {
        $guard = $this->getGuard();

        $guard->macro('foo', function () {
            return 'bar';
        });

        $this->assertSame(
            'bar', $guard->foo()
        );
    }

    public function testLoginFiresLoginAndAuthenticatedEvents()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $events = Double::for(Dispatcher::class);
        $mock->setDispatcher($events);
        $user = Double::for(Authenticatable::class);
        $events->expects('dispatch')->with(Argument::type(Login::class));
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $user->expects('getAuthIdentifier')->returns('bar');
        $mock->getSession()->expects('put')->with('foo', 'bar');
        $session->expects('regenerate');
        $mock->login($user);
    }

    public function testFailedAttemptFiresFailedEvent()
    {
        $guard = $this->getGuard();
        $events = Double::for(Dispatcher::class);
        $guard->setDispatcher($events);
        $timebox = $guard->getTimebox();
        $timebox->expects('call')->resolves(function ($callback, $microseconds) use ($timebox) {
            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Failed::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class))->never();
        $guard->getProvider()->expects('retrieveByCredentials')->with(['foo'])->returns(null);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->never();
        $guard->attempt(['foo']);
    }

    public function testAuthenticateReturnsUserWhenUserIsNotNull()
    {
        $user = Double::for(Authenticatable::class);
        $guard = $this->getGuard();
        $guard->setUser($user);

        $this->assertEquals($user, $guard->authenticate());
    }

    public function testSetUserFiresAuthenticatedEvent()
    {
        $user = Double::for(Authenticatable::class);
        $guard = $this->getGuard();
        $events = Double::for(Dispatcher::class);
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $guard->setDispatcher($events);
        $guard->setUser($user);
    }

    public function testAuthenticateThrowsWhenUserIsNull()
    {
        $this->expectExceptionObject(new AuthenticationException('Unauthenticated.'));

        $guard = $this->getGuard();
        $guard->getSession()->expects('get')->returns(null);

        $guard->authenticate();
    }

    public function testHasUserReturnsTrueWhenUserIsNotNull()
    {
        $user = Double::for(Authenticatable::class);
        $guard = $this->getGuard();
        $guard->setUser($user);

        $this->assertTrue($guard->hasUser());
    }

    public function testHasUserReturnsFalseWhenUserIsNull()
    {
        $guard = $this->getGuard();
        $guard->getSession()->expects('get')->never();

        $this->assertFalse($guard->hasUser());
    }

    public function testIsAuthedReturnsTrueWhenUserIsNotNull()
    {
        $user = Double::for(Authenticatable::class);
        $mock = $this->getGuard();
        $mock->setUser($user);
        $this->assertTrue($mock->check());
        $this->assertFalse($mock->guest());
    }

    public function testIsAuthedReturnsFalseWhenUserIsNull()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['user'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $mock->expects($this->exactly(2))->method('user')->willReturn(null);
        $this->assertFalse($mock->check());
        $this->assertTrue($mock->guest());
    }

    public function testUserMethodReturnsCachedUser()
    {
        $user = Double::for(Authenticatable::class);
        $mock = $this->getGuard();
        $mock->setUser($user);
        $this->assertSame($user, $mock->user());
    }

    public function testNullIsReturnedForUserIfNoUserFound()
    {
        $mock = $this->getGuard();
        $mock->getSession()->expects('get')->returns(null);
        $this->assertNull($mock->user());
    }

    public function testUserIsSetToRetrievedUser()
    {
        $mock = $this->getGuard();
        $mock->getSession()->expects('get')->returns(1);
        $user = Double::for(Authenticatable::class);
        $mock->getProvider()->expects('retrieveById')->with(1)->returns($user);
        $this->assertSame($user, $mock->user());
        $this->assertSame($user, $mock->getUser());
    }

    public function testLogoutRemovesSessionTokenAndRememberMeCookie()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $cookies = Double::for(CookieJar::class);
        $mock->setCookieJar($cookies);
        $user = Double::for(Authenticatable::class);
        $user->expects('getRememberToken')->returns('a');
        $user->expects('setRememberToken');
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $mock->expects($this->exactly(2))->method('getRecallerName')->willReturn($recallerName = 'bar');
        $mock->expects($this->once())->method('recaller')->willReturn('non-null-cookie');
        $provider->expects('updateRememberToken');

        $cookie = Double::for(Cookie::class);
        $cookies->expects('forget')->with('bar')->returns($cookie);
        $cookies->expects('queue')->with($cookie);
        $cookies->expects('unqueue')->with($recallerName);
        $mock->getSession()->expects('remove')->with('foo');
        $mock->setUser($user);
        $mock->logout();
        $this->assertNull($mock->getUser());
    }

    public function testLogoutDoesNotEnqueueRememberMeCookieForDeletionIfCookieDoesntExist()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $cookies = Double::for(CookieJar::class);
        $mock->setCookieJar($cookies);
        $user = Double::for(Authenticatable::class);
        $user->expects('getRememberToken')->returns(null);
        $mock->expects($this->once())->method('getRecallerName')->willReturn($recallerName = 'bar');
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $mock->expects($this->once())->method('recaller')->willReturn(null);

        $cookies->expects('unqueue')->with($recallerName);

        $mock->getSession()->expects('remove')->with('foo');
        $mock->setUser($user);
        $mock->logout();
        $this->assertNull($mock->getUser());
    }

    public function testLogoutFiresLogoutEvent()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['clearUserDataFromStorage'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $mock->expects($this->once())->method('clearUserDataFromStorage');
        $events = Double::for(Dispatcher::class);
        $mock->setDispatcher($events);
        $user = Double::for(Authenticatable::class);
        $user->expects('getRememberToken')->returns(null);
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $mock->setUser($user);
        $events->expects('dispatch')->with(Argument::type(Logout::class));
        $mock->logout();
    }

    public function testLogoutDoesNotSetRememberTokenIfNotPreviouslySet()
    {
        [$session, $provider, $request] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['clearUserDataFromStorage'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $user = Double::for(Authenticatable::class);

        $user->expects('getRememberToken')->returns(null);
        $user->expects('setRememberToken')->never();
        $provider->expects('updateRememberToken')->never();

        $mock->setUser($user);
        $mock->logout();
    }

    public function testLogoutCurrentDeviceRemovesRememberMeCookie()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $cookies = Double::for(CookieJar::class);
        $mock->setCookieJar($cookies);
        $user = Double::for(Authenticatable::class);
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $mock->expects($this->exactly(2))->method('getRecallerName')->willReturn($recallerName = 'bar');
        $mock->expects($this->once())->method('recaller')->willReturn('non-null-cookie');

        $cookie = Double::for(Cookie::class);
        $cookies->expects('forget')->with('bar')->returns($cookie);
        $cookies->expects('queue')->with($cookie);
        $cookies->expects('unqueue')->with($recallerName);
        $mock->getSession()->expects('remove')->with('foo');
        $mock->setUser($user);
        $mock->logoutCurrentDevice();
        $this->assertNull($mock->getUser());
    }

    public function testLogoutCurrentDeviceDoesNotEnqueueRememberMeCookieForDeletionIfCookieDoesntExist()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $cookies = Double::for(CookieJar::class);
        $mock->setCookieJar($cookies);
        $user = Double::for(Authenticatable::class);
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $mock->expects($this->once())->method('getRecallerName')->willReturn($recallerName = 'bar');
        $mock->expects($this->once())->method('recaller')->willReturn(null);
        $cookies->expects('unqueue')->with($recallerName);

        $mock->getSession()->expects('remove')->with('foo');
        $mock->setUser($user);
        $mock->logoutCurrentDevice();
        $this->assertNull($mock->getUser());
    }

    public function testLogoutCurrentDeviceFiresLogoutEvent()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['clearUserDataFromStorage'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $mock->expects($this->once())->method('clearUserDataFromStorage');
        $events = Double::for(Dispatcher::class);
        $mock->setDispatcher($events);
        $user = Double::for(Authenticatable::class);
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $mock->setUser($user);
        $events->expects('dispatch')->with(Argument::type(CurrentDeviceLogout::class));
        $mock->logoutCurrentDevice();
    }

    public function testLoginMethodQueuesCookieWhenRemembering()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = new SessionGuard('default', $provider, $session, $request);
        $guard->setCookieJar($cookie);
        $foreverCookie = new Cookie($guard->getRecallerName(), 'foo');
        $expectedHash = hash_hmac('sha256', 'bar', 'base-key-for-password-hash-mac');
        $cookie->expects('make')->with($guard->getRecallerName(), 'foo|recaller|'.$expectedHash, 576000)->returns($foreverCookie);
        $cookie->expects('queue')->with($foreverCookie);
        $guard->getSession()->expects('put')->with($guard->getName(), 'foo');
        $session->expects('regenerate');
        $user = Double::for(Authenticatable::class);
        $user->expects('getAuthIdentifier')->times(2)->returns('foo');
        $user->expects('getAuthPassword')->returns('bar');
        $user->expects('getRememberToken')->times(2)->returns('recaller');
        $user->expects('setRememberToken')->never();
        $provider->expects('updateRememberToken')->never();
        $guard->login($user, true);
    }

    public function testLoginMethodQueuesCookieWhenRememberingAndAllowsOverride()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = new SessionGuard('default', $provider, $session, $request);
        $guard->setRememberDuration(5000);
        $guard->setCookieJar($cookie);
        $foreverCookie = new Cookie($guard->getRecallerName(), 'foo');
        $expectedHash = hash_hmac('sha256', 'bar', 'base-key-for-password-hash-mac');
        $cookie->expects('make')->with($guard->getRecallerName(), 'foo|recaller|'.$expectedHash, 5000)->returns($foreverCookie);
        $cookie->expects('queue')->with($foreverCookie);
        $guard->getSession()->expects('put')->with($guard->getName(), 'foo');
        $session->expects('regenerate');
        $user = Double::for(Authenticatable::class);
        $user->expects('getAuthIdentifier')->times(2)->returns('foo');
        $user->expects('getAuthPassword')->returns('bar');
        $user->expects('getRememberToken')->times(2)->returns('recaller');
        $user->expects('setRememberToken')->never();
        $provider->expects('updateRememberToken')->never();
        $guard->login($user, true);
    }

    public function testLoginMethodCreatesRememberTokenIfOneDoesntExist()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = new SessionGuard('default', $provider, $session, $request);
        $guard->setCookieJar($cookie);
        $foreverCookie = new Cookie($guard->getRecallerName(), 'foo');
        $cookie->expects('make')->returns($foreverCookie);
        $cookie->expects('queue')->with($foreverCookie);
        $guard->getSession()->expects('put')->with($guard->getName(), 'foo');
        $session->expects('regenerate');
        $user = Double::for(Authenticatable::class);
        $user->expects('getAuthIdentifier')->times(2)->returns('foo');
        $user->expects('getAuthPassword')->returns('foo');
        $user->expects('getRememberToken')->times(2)->returns(null);
        $user->expects('setRememberToken');
        $provider->expects('updateRememberToken');
        $guard->login($user, true);
    }

    public function testLoginUsingIdLogsInWithUser()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();

        $guard = Double::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $user = Double::for(Authenticatable::class);
        $guard->getProvider()->expects('retrieveById')->with(10)->returns($user);
        $guard->expects('login')->with($user, false);

        $this->assertSame($user, $guard->loginUsingId(10));
    }

    public function testLoginUsingIdFailure()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = Double::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $guard->getProvider()->expects('retrieveById')->with(11)->returns(null);
        $guard->expects('login')->never();

        $this->assertFalse($guard->loginUsingId(11));
    }

    public function testOnceUsingIdSetsUser()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = Double::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $user = Double::for(Authenticatable::class);
        $guard->getProvider()->expects('retrieveById')->with(10)->returns($user);
        $guard->expects('setUser')->with($user);

        $this->assertSame($user, $guard->onceUsingId(10));
    }

    public function testOnceUsingIdFailure()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = Double::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $guard->getProvider()->expects('retrieveById')->with(11)->returns(null);
        $guard->expects('setUser')->never();

        $this->assertFalse($guard->onceUsingId(11));
    }

    public function testUserUsesRememberCookieIfItExists()
    {
        $guard = $this->getGuard();
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $request = Request::create('/', 'GET', [], [$guard->getRecallerName() => 'id|recaller|baz']);
        $guard = new SessionGuard('default', $provider, $session, $request);
        $guard->getSession()->expects('get')->with($guard->getName())->returns(null);
        $user = Double::for(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByToken')->with('id', 'recaller')->returns($user);
        $user->expects('getAuthIdentifier')->returns('bar');
        $user->expects('getAuthPassword')->returns('baz');
        $guard->getSession()->expects('put')->with($guard->getName(), 'bar');
        $session->expects('regenerate');
        $this->assertSame($user, $guard->user());
        $this->assertTrue($guard->viaRemember());
    }

    public function testUserReturnsNullWhenRememberCookieTokenDoesNotMatchAnyUser()
    {
        $guard = $this->getGuard();
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $request = Request::create('/', 'GET', [], [$guard->getRecallerName() => 'id|recaller|baz']);
        $guard = new SessionGuard('default', $provider, $session, $request);
        $guard->getSession()->expects('get')->with($guard->getName())->returns(null);
        $guard->getProvider()->expects('retrieveByToken')->with('id', 'recaller')->returns(null);
        $this->assertNull($guard->user());
        $this->assertFalse($guard->viaRemember());
    }

    public function testLoginOnceSetsUser()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $guard = Double::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session, $request, $timebox));
        $user = Double::for(Authenticatable::class);
        $timebox->expects('call')->resolves(function ($callback) use ($timebox) {
            $timebox->expects('returnEarly');

            return $callback($timebox);
        });
        $guard->getProvider()->expects('retrieveByCredentials')->with(['foo'])->returns($user);
        $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->returns(true);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);
        $guard->expects('setUser')->with($user);
        $this->assertTrue($guard->once(['foo']));
    }

    public function testLoginOnceFailure()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $guard = Double::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session, $request, $timebox));
        $user = Double::for(Authenticatable::class);
        $timebox->expects('call')->resolves(function ($callback) use ($timebox) {
            return $callback($timebox);
        });
        $guard->getProvider()->expects('retrieveByCredentials')->with(['foo'])->returns($user);
        $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->returns(false);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->never();
        $this->assertFalse($guard->once(['foo']));
    }

    public function testForgetUserSetsUserToNull()
    {
        $user = Double::for(Authenticatable::class);
        $guard = $this->getGuard();
        $guard->setUser($user);
        $guard->forgetUser();
        $this->assertNull($guard->getUser());
    }

    protected function getGuard()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();

        return new SessionGuard('default', $provider, $session, $request, $timebox);
    }

    protected function getMocks()
    {
        return [
            Double::for(Session::class),
            Double::for(UserProvider::class),
            Request::create('/', 'GET'),
            Double::for(CookieJar::class),
            Double::for(Timebox::class),
        ];
    }

    protected function getCookieJar()
    {
        return new CookieJar(Request::create('/foo', 'GET'), Double::for(Encrypter::class), ['domain' => 'foo.com', 'path' => '/', 'secure' => false, 'httpOnly' => false]);
    }
}
