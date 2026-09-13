<?php

namespace Illuminate\Tests\Support;

use ArrayAccess;
use Illuminate\Support\Facades\Facade;
use JMac\Testing\DoubleInterface;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class SupportFacadeTest extends TestCase
{
    use VerifiesDoubles;

    protected function setUp(): void
    {
        Facade::clearResolvedInstances();
        FacadeStub::setFacadeApplication(null);
    }

    public function testFacadeCallsUnderlyingApplication()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => $mock = Mockery::mock(stdClass::class)]);
        $mock->expects('bar')->andReturn('baz');
        FacadeStub::setFacadeApplication($app);
        $this->assertSame('baz', FacadeStub::bar());
    }

    public function testShouldReceiveReturnsADouble()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new FacadeStubTarget]);
        FacadeStub::setFacadeApplication($app);

        FacadeStub::shouldReceive('foo')->with('bar')->returns('baz');

        $this->assertInstanceOf(DoubleInterface::class, $app['foo']);
        $this->assertSame('baz', $app['foo']->foo('bar'));
    }

    public function testSpyReturnsADouble()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new FacadeStubTarget]);
        FacadeStub::setFacadeApplication($app);

        $this->assertInstanceOf(DoubleInterface::class, $spy = FacadeStub::spy());

        FacadeStub::foo();
        $spy->received('foo');
    }

    public function testShouldHaveReceivedTracksCallsMadeAfterShouldReceive()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new FacadeStubTarget]);
        FacadeStub::setFacadeApplication($app);

        FacadeStub::shouldReceive('foo');

        FacadeStub::foo();
        FacadeStub::shouldHaveReceived('foo');
    }

    public function testShouldReceiveCanBeCalledTwice()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new FacadeStubTarget]);
        FacadeStub::setFacadeApplication($app);

        FacadeStub::expects('foo')->with('bar')->returns('baz');
        FacadeStub::expects('foo2')->with('bar2')->returns('baz2');

        $this->assertInstanceOf(DoubleInterface::class, $app['foo']);
        $this->assertSame('baz', $app['foo']->foo('bar'));
        $this->assertSame('baz2', $app['foo']->foo2('bar2'));
    }

    public function testCannotBeMockedWithoutUnderlyingInstance()
    {
        $this->expectException(RuntimeException::class);

        FacadeStub::expects('foo')->returns('bar');
    }

    public function testExpectsReturnsADoubleWithExpectationRequired()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => new FacadeStubTarget]);
        FacadeStub::setFacadeApplication($app);

        FacadeStub::expects('foo')->with('bar')->returns('baz');

        $this->assertInstanceOf(DoubleInterface::class, $app['foo']);
        $this->assertSame('baz', $app['foo']->foo('bar'));
    }

    public function testFacadeResolvesAgainAfterClearingSpecific()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => $mock = Mockery::mock(stdClass::class)]);
        $mock->expects('bar')->times(3)->andReturn('baz');

        // Resolve for the first time
        FacadeStub::setFacadeApplication($app);
        $this->assertSame('baz', FacadeStub::bar());

        // Clear resolved instance and resolve the second time
        FacadeStub::clearResolvedInstance();
        $this->assertSame('baz', FacadeStub::bar());

        // Clear resolved instance through parent and resolve the third time
        Facade::clearResolvedInstance('foo');
        $this->assertSame('baz', FacadeStub::bar());
    }

    public function testFacadeResolvesAgainAfterClearingAll()
    {
        $app = new ApplicationStub;
        $app->setAttributes(['foo' => $mock = Mockery::mock(stdClass::class)]);
        $mock->expects('bar')->times(2)->andReturn('baz');

        // Resolve for the first time
        FacadeStub::setFacadeApplication($app);
        $this->assertSame('baz', FacadeStub::bar());

        // Clear all resolved instances and resolve a second time
        Facade::clearResolvedInstances();
        $this->assertSame('baz', FacadeStub::bar());
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
    public function foo($arg = null) {}

    public function foo2($arg = null) {}
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
