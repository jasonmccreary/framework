<?php

namespace Illuminate\Tests\Auth;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use JMac\Testing\TestDouble;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthPasswordBrokerManagerTest extends TestCase
{
    public function testBrokerCanResolveBackedEnum(): void
    {
        $app = $this->getApp();

        $broker = TestDouble::for(PasswordBroker::class);

        // TODO: no direct TestDouble equivalent — needs manual review. `resolve()` is a
        // protected method invoked internally (via `$this->resolve()`) from `broker()`.
        // TestDouble::for()->passthru() delegates unstubbed calls to a separate real
        // instance, so a self-call made from inside that real instance's own method never
        // routes back through the double's `allows()`/`expects()` interception the way a
        // Mockery partial mock (a single self-referencing object) does.
        $manager = Mockery::mock(PasswordBrokerManager::class, [$app])->makePartial()->shouldAllowMockingProtectedMethods();
        $manager->allows('resolve')->with('users')->andReturn($broker);

        $result1 = $manager->broker(PasswordBrokerName::Users);
        $result2 = $manager->broker('users');

        $this->assertSame($broker, $result1);
        $this->assertSame($result1, $result2);
    }

    public function testSetDefaultDriverAcceptsBackedEnum(): void
    {
        $app = $this->getApp();

        $manager = new PasswordBrokerManager($app);
        $manager->setDefaultDriver(PasswordBrokerName::Users);

        $this->assertSame('users', $app['config']['auth.defaults.passwords']);
    }

    protected function getApp(): Container
    {
        $app = new Container;

        $app->singleton('config', fn () => new Config([
            'auth' => [
                'defaults' => ['passwords' => 'users'],
                'passwords' => [
                    'users' => [
                        'provider' => 'users',
                        'table' => 'password_reset_tokens',
                        'expire' => 60,
                        'throttle' => 60,
                    ],
                ],
            ],
        ]));

        return $app;
    }
}

enum PasswordBrokerName: string
{
    case Users = 'users';
}
