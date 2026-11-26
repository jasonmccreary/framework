<?php

namespace Illuminate\Tests\Support;

use ArrayAccess;
use Illuminate\Support\Facades\Facade;
use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SupportFacadeTest extends TestCase
{
    use VerifiesDoubles;

    protected function setUp(): void
    {
        Facade::clearResolvedInstances();
        FacadeStub::setFacadeApplication(null);
    }

    protected function setUpApplication(): ApplicationStub
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new FacadeStubTarget]);
        FacadeStub::setFacadeApplication($app);

        return $app;
    }

    public function testFacadeCallsUnderlyingApplication()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new class
        {
            public function bar()
            {
                return 'baz';
            }
        }]);
        FacadeStub::setFacadeApplication($app);
        $this->assertSame('baz', FacadeStub::bar());
    }

    public function testShouldReceiveSwapsInADouble()
    {
        $app = $this->setUpApplication();

        FacadeStub::shouldReceive('foo')->with('bar')->returns('baz');

        $this->assertInstanceOf(DoubleInterface::class, $app['foo']);
        $this->assertInstanceOf(FacadeStubTarget::class, $app['foo']);
        $this->assertSame('baz', FacadeStub::foo('bar'));
    }

    public function testShouldReceiveCreatesAStrictDouble()
    {
        $this->setUpApplication();

        FacadeStub::shouldReceive('foo');

        $this->expectException(PHPUnitUnexpectedCallException::class);

        FacadeStub::foo2();
    }

    public function testSpyReturnsADouble()
    {
        $app = $this->setUpApplication();

        $this->assertInstanceOf(DoubleInterface::class, $spy = FacadeStub::spy());
        $this->assertSame($spy, $app['foo']);

        $this->assertNull(FacadeStub::foo());

        $spy->received('foo');
        FacadeStub::shouldHaveReceived('foo');
    }

    public function testShouldReceiveCanBeCalledTwice()
    {
        $app = $this->setUpApplication();

        FacadeStub::expects('foo')->with('bar')->returns('baz');
        FacadeStub::expects('foo2')->with('bar2')->returns('baz2');

        $this->assertSame('baz', $app['foo']->foo('bar'));
        $this->assertSame('baz2', $app['foo']->foo2('bar2'));
    }

    public function testCannotBeMockedWithoutUnderlyingInstance()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not have a resolvable root');

        FacadeStub::expects('foo');
    }

    public function testExpectsReturnsADoubleWithExpectationRequired()
    {
        $app = $this->setUpApplication();

        FacadeStub::expects('foo')->with('bar')->returns('baz');

        $this->assertInstanceOf(DoubleInterface::class, $app['foo']);
        $this->assertSame('baz', $app['foo']->foo('bar'));
    }

    public function testShouldNotReceive()
    {
        $this->setUpApplication();

        FacadeStub::shouldReceive('foo');
        FacadeStub::shouldNotReceive('foo2');

        FacadeStub::foo();

        $this->expectException(AssertionFailedError::class);

        FacadeStub::foo2();
    }

    public function testShouldHaveReceivedWithArguments()
    {
        $this->setUpApplication();

        FacadeStub::spy();

        FacadeStub::foo('bar');

        FacadeStub::shouldHaveReceived('foo', ['bar']);
        FacadeStub::shouldHaveReceived('foo')->with('bar')->times(1);
        FacadeStub::shouldNotHaveReceived('foo', ['baz']);
        FacadeStub::shouldNotHaveReceived('foo2');
    }

    public function testShouldHaveReceivedRequiresAMock()
    {
        $this->setUpApplication();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be spied or mocked');

        FacadeStub::shouldHaveReceived('foo');
    }

    public function testPartialMockPassesThroughToTheRealInstance()
    {
        $this->setUpApplication();

        FacadeStub::partialMock()->allows('foo2')->returns('baz');

        $this->assertSame('real', FacadeStub::foo());
        $this->assertSame('baz', FacadeStub::foo2());
    }

    public function testSwappedDoubleIsConfiguredByTheFacade()
    {
        $app = $this->setUpApplication();

        FacadeStub::swap($double = Double::for(FacadeStubTarget::class));
        FacadeStub::shouldReceive('foo')->returns('baz');

        $this->assertSame($double, $app['foo']);
        $this->assertSame('baz', FacadeStub::foo());
    }

    public function testDoubleIsReusedAfterClearingResolvedInstances()
    {
        $this->setUpApplication();

        FacadeStub::shouldReceive('foo')->returns('baz');

        Facade::clearResolvedInstances();

        FacadeStub::shouldReceive('foo2')->returns('baz2');

        $this->assertSame('baz', FacadeStub::foo());
        $this->assertSame('baz2', FacadeStub::foo2());
    }

    public function testRootWithReservedMethodNamesCanBeMocked()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new FacadeStubTargetWithReservedNames]);
        FacadeStub::setFacadeApplication($app);

        FacadeStub::expects('allows')->with('ability')->returns(true);

        $this->assertInstanceOf(FacadeStubTargetWithReservedNames::class, $app['foo']);
        $this->assertTrue(FacadeStub::allows('ability'));

        FacadeStub::shouldHaveReceived('allows')->with('ability');
    }

    public function testFacadeResolvesAgainAfterClearingSpecific()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => $service = new FacadeTestService]);

        // Resolve for the first time
        FacadeStub::setFacadeApplication($app);
        $this->assertSame('baz', FacadeStub::bar());

        // Clear resolved instance and resolve the second time
        FacadeStub::clearResolvedInstance();
        $this->assertSame('baz', FacadeStub::bar());

        // Clear resolved instance through parent and resolve the third time
        Facade::clearResolvedInstance('foo');
        $this->assertSame('baz', FacadeStub::bar());

        $this->assertSame(3, $service->calls);
    }

    public function testFacadeResolvesAgainAfterClearingAll()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => $service = new FacadeTestService]);

        // Resolve for the first time
        FacadeStub::setFacadeApplication($app);
        $this->assertSame('baz', FacadeStub::bar());

        // Clear all resolved instances and resolve a second time
        Facade::clearResolvedInstances();
        $this->assertSame('baz', FacadeStub::bar());

        $this->assertSame(2, $service->calls);
    }
}

class FacadeStub extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'foo';
    }
}

class FacadeStubTarget
{
    public function foo($arg = null)
    {
        return 'real';
    }

    public function foo2($arg = null)
    {
        return 'real';
    }
}

class FacadeStubTargetWithReservedNames
{
    public function allows($ability)
    {
        return false;
    }
}

class ApplicationStub implements ArrayAccess
{
    protected $attributes = [];

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function instance($key, $instance)
    {
        $this->attributes[$key] = $instance;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($key): mixed
    {
        return $this->attributes[$key];
    }

    public function offsetSet($key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function offsetUnset($key): void
    {
        unset($this->attributes[$key]);
    }
}

class FacadeTestService
{
    public int $calls = 0;

    public function bar()
    {
        $this->calls++;

        return 'baz';
    }
}
