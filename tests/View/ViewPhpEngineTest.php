<?php

namespace Illuminate\Tests\View;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Tests\TestCase;
use Illuminate\View\Engines\PhpEngine;

class ViewPhpEngineTest extends TestCase
{
    public function testViewsMayBeProperlyRendered()
    {
        $engine = new PhpEngine(new Filesystem);
        $this->assertSame('Hello World
', $engine->get(__DIR__.'/Fixtures/basic.php'));
    }
}
