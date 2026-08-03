<?php

namespace Illuminate\Tests\Auth;

use JMac\Testing\TestDouble;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User;
use PHPUnit\Framework\TestCase;

class AuthListenersSendEmailVerificationNotificationHandleFunctionTest extends TestCase
{
    /**
     * @return void
     */
    public function testWillExecuted()
    {
        $user = $this->createMock(MustVerifyEmail::class);
        $user->method('hasVerifiedEmail')->willReturn(false);
        $user->expects($this->once())->method('sendEmailVerificationNotification');

        $listener = new SendEmailVerificationNotification;

        $listener->handle(new Registered($user));
    }

    /**
     * @return void
     */
    public function testUserIsNotInstanceOfMustVerifyEmail()
    {
        $user = TestDouble::for(User::class);
        $user->expects('sendEmailVerificationNotification')->never();

        $listener = new SendEmailVerificationNotification;

        $listener->handle(new Registered($user));
    }

    /**
     * @return void
     */
    public function testHasVerifiedEmailAsTrue()
    {
        $user = $this->createMock(MustVerifyEmail::class);
        $user->method('hasVerifiedEmail')->willReturn(true);
        $user->expects($this->never())->method('sendEmailVerificationNotification');

        $listener = new SendEmailVerificationNotification;

        $listener->handle(new Registered($user));
    }
}
