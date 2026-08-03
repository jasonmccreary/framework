<?php

namespace Illuminate\Tests\Auth;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthGuardTest extends TestCase
{
    public function testBasicReturnsNullOnValidAttempt()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));
        $guard->expects('check')->returns(false);
        $guard->expects('attempt')->with(['email' => 'foo@bar.com', 'password' => 'secret'])->returns(true);
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email');
    }

    public function testBasicReturnsNullWhenAlreadyLoggedIn()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));
        $guard->expects('check')->returns(true);
        $guard->expects('attempt')->never();
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email');
    }

    public function testBasicReturnsResponseOnFailure()
    {
        $this->expectException(UnauthorizedHttpException::class);

        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));
        $guard->expects('check')->returns(false);
        $guard->expects('attempt')->with(['email' => 'foo@bar.com', 'password' => 'secret'])->returns(false);
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);
        $guard->basic('email');
    }

    public function testBasicWithExtraConditions()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));
        $guard->expects('check')->returns(false);
        $guard->expects('attempt')->with(['email' => 'foo@bar.com', 'password' => 'secret', 'active' => 1])->returns(true);
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email', ['active' => 1]);
    }

    public function testBasicWithExtraArrayConditions()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));
        $guard->expects('check')->returns(false);
        $guard->expects('attempt')->with(['email' => 'foo@bar.com', 'password' => 'secret', 'active' => 1, 'type' => [1, 2, 3]])->returns(true);
        $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']);
        $guard->setRequest($request);

        $guard->basic('email', ['active' => 1, 'type' => [1, 2, 3]]);
    }

    public function testAttemptCallsRetrieveByCredentials()
    {
        $guard = $this->getGuard();
        $guard->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $timebox = $guard->getTimebox();
        $timebox->expects('call')->resolves(function ($callback) use ($timebox) {
            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Failed::class));
        $events->shouldNotReceive('dispatch')->with(Argument::type(Validated::class));
        $guard->getProvider()->expects('retrieveByCredentials')->with(['foo']);
        $guard->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
        $guard->attempt(['foo']);
    }

    public function testAttemptReturnsUserInterface()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $guard = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['login'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
        $guard->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $timebox->shouldReceive('call')->once()->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
            return $callback($timebox->shouldReceive('returnEarly')->once()->getMock());
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class));
        $user = $this->createStub(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByCredentials')->returns($user);
        $guard->getProvider()->allows('validateCredentials')->with($user, ['foo'])->returns(true);
        $guard->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);
        $guard->expects($this->once())->method('login')->with($user);
        $this->assertTrue($guard->attempt(['foo']));
    }

    public function testAttemptReturnsFalseIfUserNotGiven()
    {
        $mock = $this->getGuard();
        $mock->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $timebox = $mock->getTimebox();
        $timebox->expects('call')->resolves(function ($callback, $microseconds) use ($timebox) {
            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Failed::class));
        $events->shouldNotReceive('dispatch')->with(Argument::type(Validated::class));
        $mock->getProvider()->expects('retrieveByCredentials')->returns(null);
        $mock->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
        $this->assertFalse($mock->attempt(['foo']));
    }

    public function testAttemptAndWithCallbacks()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
        $mock->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $timebox->shouldReceive('call')->andReturnUsing(function ($callback) use ($timebox) {
            return $callback($timebox->shouldReceive('returnEarly')->getMock());
        });
        $user = TestDouble::for(Authenticatable::class);
        $events->expects('dispatch')->times(3)->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Login::class));
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $events->shouldReceive('dispatch')->twice()->with(Argument::type(Validated::class));
        $events->shouldReceive('dispatch')->twice()->with(Argument::type(Failed::class));
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $user->expects('getAuthIdentifier')->returns('bar');
        $mock->getSession()->expects('put')->with('foo', 'bar');
        $session->expects('regenerate');
        $mock->getProvider()->expects('retrieveByCredentials')->times(3)->with(['foo'])->returns($user);
        $mock->getProvider()->shouldReceive('validateCredentials')->twice()->andReturnTrue();
        $mock->getProvider()->expects('validateCredentials')->returns(false);
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
        $guard->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $timebox->shouldReceive('call')->once()->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
            return $callback($timebox->shouldReceive('returnEarly')->once()->getMock());
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class));
        $user = $this->createStub(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByCredentials')->returns($user);
        $guard->getProvider()->allows('validateCredentials')->with($user, ['foo'])->returns(true);
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
        $guard->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $timebox->shouldReceive('call')->once()->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
            return $callback($timebox->shouldReceive('returnEarly')->once()->getMock());
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Validated::class));
        $user = $this->createStub(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByCredentials')->returns($user);
        $guard->getProvider()->allows('validateCredentials')->with($user, ['foo'])->returns(true);
        $guard->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
        $guard->expects($this->once())->method('login')->with($user);
        $this->assertTrue($guard->attempt(['foo']));
    }

    public function testLoginStoresIdentifierInSession()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $user = TestDouble::for(Authenticatable::class);
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
        $mock->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $user = TestDouble::for(Authenticatable::class);
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
        $guard->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $timebox = $guard->getTimebox();
        $timebox->expects('call')->resolves(function ($callback, $microseconds) use ($timebox) {
            return $callback($timebox);
        });
        $events->expects('dispatch')->with(Argument::type(Attempting::class));
        $events->expects('dispatch')->with(Argument::type(Failed::class));
        $events->shouldNotReceive('dispatch')->with(Argument::type(Validated::class));
        $guard->getProvider()->expects('retrieveByCredentials')->with(['foo'])->returns(null);
        $guard->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
        $guard->attempt(['foo']);
    }

    public function testAuthenticateReturnsUserWhenUserIsNotNull()
    {
        $user = TestDouble::for(Authenticatable::class);
        $guard = $this->getGuard();
        $guard->setUser($user);

        $this->assertEquals($user, $guard->authenticate());
    }

    public function testSetUserFiresAuthenticatedEvent()
    {
        $user = TestDouble::for(Authenticatable::class);
        $guard = $this->getGuard();
        $guard->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $guard->setUser($user);
    }

    public function testAuthenticateThrowsWhenUserIsNull()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthenticated.');

        $guard = $this->getGuard();
        $guard->getSession()->expects('get')->returns(null);

        $guard->authenticate();
    }

    public function testHasUserReturnsTrueWhenUserIsNotNull()
    {
        $user = TestDouble::for(Authenticatable::class);
        $guard = $this->getGuard();
        $guard->setUser($user);

        $this->assertTrue($guard->hasUser());
    }

    public function testHasUserReturnsFalseWhenUserIsNull()
    {
        $guard = $this->getGuard();
        $guard->getSession()->shouldNotReceive('get');

        $this->assertFalse($guard->hasUser());
    }

    public function testIsAuthedReturnsTrueWhenUserIsNotNull()
    {
        $user = TestDouble::for(Authenticatable::class);
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
        $user = TestDouble::for(Authenticatable::class);
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
        $user = TestDouble::for(Authenticatable::class);
        $mock->getProvider()->expects('retrieveById')->with(1)->returns($user);
        $this->assertSame($user, $mock->user());
        $this->assertSame($user, $mock->getUser());
    }

    public function testLogoutRemovesSessionTokenAndRememberMeCookie()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $mock->setCookieJar($cookies = TestDouble::for(CookieJar::class));
        $user = TestDouble::for(Authenticatable::class);
        $user->expects('getRememberToken')->returns('a');
        $user->expects('setRememberToken');
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $mock->expects($this->exactly(2))->method('getRecallerName')->willReturn($recallerName = 'bar');
        $mock->expects($this->once())->method('recaller')->willReturn('non-null-cookie');
        $provider->expects('updateRememberToken');

        $cookie = TestDouble::for(Cookie::class);
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
        $mock->setCookieJar($cookies = TestDouble::for(CookieJar::class));
        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getRememberToken')->returns(null);
        $mock->expects($this->once())->method('getRecallerName')->willReturn($recallerName = 'bar');
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $mock->expects($this->once())->method('recaller')->willReturn(null);

        $cookies->allows('unqueue')->with($recallerName);

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
        $mock->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getRememberToken')->returns(null);
        $events->expects('dispatch')->with(Argument::type(Authenticated::class));
        $mock->setUser($user);
        $events->expects('dispatch')->with(Argument::type(Logout::class));
        $mock->logout();
    }

    public function testLogoutDoesNotSetRememberTokenIfNotPreviouslySet()
    {
        [$session, $provider, $request] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['clearUserDataFromStorage'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $user = TestDouble::for(Authenticatable::class);

        $user->allows('getRememberToken')->returns(null);
        $user->shouldNotReceive('setRememberToken');
        $provider->shouldNotReceive('updateRememberToken');

        $mock->setUser($user);
        $mock->logout();
    }

    public function testLogoutCurrentDeviceRemovesRememberMeCookie()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
        $mock->setCookieJar($cookies = TestDouble::for(CookieJar::class));
        $user = TestDouble::for(Authenticatable::class);
        $mock->expects($this->once())->method('getName')->willReturn('foo');
        $mock->expects($this->exactly(2))->method('getRecallerName')->willReturn($recallerName = 'bar');
        $mock->expects($this->once())->method('recaller')->willReturn('non-null-cookie');

        $cookie = TestDouble::for(Cookie::class);
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
        $mock->setCookieJar($cookies = TestDouble::for(CookieJar::class));
        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getRememberToken')->returns(null);
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
        $mock->setDispatcher($events = TestDouble::for(Dispatcher::class));
        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getRememberToken')->returns(null);
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
        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getAuthIdentifier')->returns('foo');
        $user->allows('getAuthPassword')->returns('bar');
        $user->allows('getRememberToken')->returns('recaller');
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
        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getAuthIdentifier')->returns('foo');
        $user->allows('getAuthPassword')->returns('bar');
        $user->allows('getRememberToken')->returns('recaller');
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
        $user = TestDouble::for(Authenticatable::class);
        $user->allows('getAuthIdentifier')->returns('foo');
        $user->allows('getAuthPassword')->returns('foo');
        $user->allows('getRememberToken')->returns(null);
        $user->expects('setRememberToken');
        $provider->expects('updateRememberToken');
        $guard->login($user, true);
    }

    public function testLoginUsingIdLogsInWithUser()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();

        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $user = TestDouble::for(Authenticatable::class);
        $guard->getProvider()->expects('retrieveById')->with(10)->returns($user);
        $guard->expects('login')->with($user, false);

        $this->assertSame($user, $guard->loginUsingId(10));
    }

    public function testLoginUsingIdFailure()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $guard->getProvider()->expects('retrieveById')->with(11)->returns(null);
        $guard->shouldNotReceive('login');

        $this->assertFalse($guard->loginUsingId(11));
    }

    public function testOnceUsingIdSetsUser()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $user = TestDouble::for(Authenticatable::class);
        $guard->getProvider()->expects('retrieveById')->with(10)->returns($user);
        $guard->expects('setUser')->with($user);

        $this->assertSame($user, $guard->onceUsingId(10));
    }

    public function testOnceUsingIdFailure()
    {
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session));

        $guard->getProvider()->expects('retrieveById')->with(11)->returns(null);
        $guard->shouldNotReceive('setUser');

        $this->assertFalse($guard->onceUsingId(11));
    }

    public function testUserUsesRememberCookieIfItExists()
    {
        $guard = $this->getGuard();
        [$session, $provider, $request, $cookie] = $this->getMocks();
        $request = Request::create('/', 'GET', [], [$guard->getRecallerName() => 'id|recaller|baz']);
        $guard = new SessionGuard('default', $provider, $session, $request);
        $guard->getSession()->expects('get')->with($guard->getName())->returns(null);
        $user = TestDouble::for(Authenticatable::class);
        $guard->getProvider()->expects('retrieveByToken')->with('id', 'recaller')->returns($user);
        $user->expects('getAuthIdentifier')->returns('bar');
        $guard->getSession()->expects('put')->with($guard->getName(), 'bar');
        $session->expects('regenerate');
        $this->assertSame($user, $guard->user());
        $this->assertTrue($guard->viaRemember());
    }

    public function testLoginOnceSetsUser()
    {
        [$session, $provider, $request, $cookie, $timebox] = $this->getMocks();
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session, $request, $timebox));
        $user = TestDouble::for(Authenticatable::class);
        $timebox->shouldReceive('call')->once()->andReturnUsing(function ($callback) use ($timebox) {
            return $callback($timebox->shouldReceive('returnEarly')->once()->getMock());
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
        $guard = TestDouble::for(SessionGuard::class)->passthru(new SessionGuard('default', $provider, $session, $request, $timebox));
        $user = TestDouble::for(Authenticatable::class);
        $timebox->expects('call')->resolves(function ($callback) use ($timebox) {
            return $callback($timebox);
        });
        $guard->getProvider()->expects('retrieveByCredentials')->with(['foo'])->returns($user);
        $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->returns(false);
        $guard->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
        $this->assertFalse($guard->once(['foo']));
    }

    public function testForgetUserSetsUserToNull()
    {
        $user = TestDouble::for(Authenticatable::class);
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
            TestDouble::for(Session::class),
            TestDouble::for(UserProvider::class),
            Request::create('/', 'GET'),
            TestDouble::for(CookieJar::class),
            TestDouble::for(Timebox::class),
        ];
    }

    protected function getCookieJar()
    {
        return new CookieJar(Request::create('/foo', 'GET'), TestDouble::for(Encrypter::class), ['domain' => 'foo.com', 'path' => '/', 'secure' => false, 'httpOnly' => false]);
    }
}
