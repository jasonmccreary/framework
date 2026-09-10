<?php

namespace Illuminate\Tests\View;

use Illuminate\Tests\TestCase;
use Illuminate\View\Engines\EngineResolver;
use InvalidArgumentException;
use stdClass;

class ViewEngineResolverTest extends TestCase
{
    public function testResolversMayBeResolved()
    {
        $resolver = new EngineResolver;
        $resolver->register('foo', function () {
            return new stdClass;
        });
        $result = $resolver->resolve('foo');

        $this->assertEquals(spl_object_id($result), spl_object_id($resolver->resolve('foo')));
    }

    public function testResolverThrowsExceptionOnUnknownEngine()
    {
        $this->expectException(InvalidArgumentException::class);

        $resolver = new EngineResolver;
        $resolver->resolve('foo');
    }
}
