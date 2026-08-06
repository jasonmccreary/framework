<?php

namespace Illuminate\Tests\Foundation\Bootstrap;

use JMac\Testing\TestDouble;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use PHPUnit\Framework\TestCase;

class LoadEnvironmentVariablesTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['FOO'], $_SERVER['FOO']);
        putenv('FOO');

        parent::tearDown();
    }

    protected function getAppMock($file)
    {
        $app = TestDouble::for(Application::class);

        $app->expects('configurationIsCached')->with()->returns(false);
        $app->expects('runningInConsole')->with()->returns(false);
        $app->expects('environmentPath')->with()->returns(__DIR__.'/../fixtures');
        $app->expects('environmentFile')->with()->returns($file);

        return $app;
    }

    public function testCanLoad()
    {
        $this->expectOutputString('');

        (new LoadEnvironmentVariables)->bootstrap($this->getAppMock('.env'));

        $this->assertSame('BAR', env('FOO'));
        $this->assertSame('BAR', getenv('FOO'));
        $this->assertSame('BAR', $_ENV['FOO']);
        $this->assertSame('BAR', $_SERVER['FOO']);
    }

    public function testCanFailSilent()
    {
        $this->expectOutputString('');

        (new LoadEnvironmentVariables)->bootstrap($this->getAppMock('BAD_FILE'));
    }
}
