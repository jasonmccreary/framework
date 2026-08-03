<?php

namespace Illuminate\Tests\Support;

use JMac\Testing\TestDouble;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\CapsuleManagerTrait;
use PHPUnit\Framework\TestCase;

class SupportCapsuleManagerTraitTest extends TestCase
{
    use CapsuleManagerTrait;

    public function testSetupContainerForCapsule()
    {
        $this->container = null;
        $app = new Container;

        $this->setupContainer($app);
        $this->assertEquals($app, $this->getContainer());
        $this->assertInstanceOf(Fluent::class, $app['config']);
    }

    public function testSetupContainerForCapsuleWhenConfigIsBound()
    {
        $this->container = null;
        $app = new Container;
        $app['config'] = TestDouble::for(Repository::class);

        $this->setupContainer($app);
        $this->assertEquals($app, $this->getContainer());
        $this->assertInstanceOf(Repository::class, $app['config']);
    }
}
