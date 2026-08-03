<?php

namespace Illuminate\Tests\Auth;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Arr;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class AuthPasswordBrokerTest extends TestCase
{
    public function testIfUserIsNotFoundErrorRedirectIsReturned()
    {
        $mocks = $this->getMocks();
        $broker = $this->getBroker($mocks);
        $mocks['users']->expects('retrieveByCredentials')->with(['credentials'])->returns(null);

        $this->assertSame(PasswordBrokerContract::INVALID_USER, $broker->sendResetLink(['credentials']));
    }

    public function testIfTokenIsRecentlyCreated()
    {
        $mocks = $this->getMocks();
        $broker = $this->getBroker($mocks);
        $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->returns($user = TestDouble::for(CanResetPassword::class));
        $mocks['tokens']->expects('recentlyCreatedToken')->with($user)->returns(true);
        $user->allows('sendPasswordResetNotification')->with('token');

        $this->assertSame(PasswordBrokerContract::RESET_THROTTLED, $broker->sendResetLink(['foo']));
    }

    public function testGetUserThrowsExceptionIfUserDoesntImplementCanResetPassword()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('User must implement CanResetPassword interface.');

        $broker = $this->getBroker($mocks = $this->getMocks());
        $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->returns('bar');

        $broker->getUser(['foo']);
    }

    public function testUserIsRetrievedByCredentials()
    {
        $broker = $this->getBroker($mocks = $this->getMocks());
        $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->returns($user = TestDouble::for(CanResetPassword::class));

        $this->assertEquals($user, $broker->getUser(['foo']));
    }

    public function testBrokerCreatesTokenAndRedirectsWithoutError()
    {
        $mocks = $this->getMocks();
        $broker = $this->getBroker($mocks);
        $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->returns($user = TestDouble::for(CanResetPassword::class));
        $mocks['tokens']->expects('recentlyCreatedToken')->with($user)->returns(false);
        $mocks['tokens']->expects('create')->with($user)->returns('token');
        $user->allows('sendPasswordResetNotification')->with('token');

        $this->assertSame(PasswordBrokerContract::RESET_LINK_SENT, $broker->sendResetLink(['foo']));
    }

    public function testRedirectIsReturnedByResetWhenUserCredentialsInvalid()
    {
        $broker = $this->getBroker($mocks = $this->getMocks());
        $mocks['users']->expects('retrieveByCredentials')->with(['creds'])->returns(null);

        $this->assertSame(PasswordBrokerContract::INVALID_USER, $broker->reset(['creds'], function () {
            //
        }));
    }

    public function testRedirectReturnedByRemindWhenRecordDoesntExistInTable()
    {
        $creds = ['token' => 'token'];
        $broker = $this->getBroker($mocks = $this->getMocks());
        $mocks['users']->expects('retrieveByCredentials')->with(Arr::except($creds, ['token']))->returns($user = TestDouble::for(CanResetPassword::class));
        $mocks['tokens']->allows('exists')->with($user, 'token')->returns(false);

        $this->assertSame(PasswordBrokerContract::INVALID_TOKEN, $broker->reset($creds, function () {
            //
        }));
    }

    public function testResetRemovesRecordOnReminderTableAndCallsCallback()
    {
        unset($_SERVER['__password.reset.test']);
        $mocks = $this->getMocks();
        $broker = $this->getBroker($mocks);
        $mocks['users']->expects('retrieveByCredentials')->with(['password' => 'password'])->returns($user = TestDouble::for(CanResetPassword::class));
        $mocks['tokens']->expects('exists')->with($user, 'token')->returns(true);
        $mocks['tokens']->expects('delete')->with($user);
        $callback = function ($user, $password) {
            $_SERVER['__password.reset.test'] = ['user' => $user, 'password' => $password];

            return 'foo';
        };

        $this->assertSame(PasswordBrokerContract::PASSWORD_RESET, $broker->reset(['password' => 'password', 'token' => 'token'], $callback));
        $this->assertEquals(['user' => $user, 'password' => 'password'], $_SERVER['__password.reset.test']);
    }

    public function testExecutesCallbackInsteadOfSendingNotification()
    {
        $executed = false;

        $closure = function () use (&$executed) {
            $executed = true;
        };

        $mocks = $this->getMocks();
        $broker = $this->getBroker($mocks);
        $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->returns($user = TestDouble::for(CanResetPassword::class));
        $mocks['tokens']->expects('recentlyCreatedToken')->with($user)->returns(false);
        $mocks['tokens']->expects('create')->with($user)->returns('token');
        $user->allows('sendPasswordResetNotification')->with('token');

        $this->assertEquals(PasswordBrokerContract::RESET_LINK_SENT, $broker->sendResetLink(['foo'], $closure));

        $this->assertTrue($executed);
    }

    protected function getBroker($mocks)
    {
        return new PasswordBroker($mocks['tokens'], $mocks['users']);
    }

    protected function getMocks()
    {
        return [
            'tokens' => TestDouble::for(TokenRepositoryInterface::class),
            'users' => TestDouble::for(UserProvider::class),
        ];
    }
}
