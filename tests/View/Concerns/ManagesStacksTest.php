<?php

namespace Illuminate\Tests\View\Concerns;

use Illuminate\Tests\TestCase;
use Illuminate\View\Concerns\ManagesStacks;

class ManagesStacksTest extends TestCase
{
    public function testStackIsEmpty()
    {
        $this->assertTrue((new FakeViewFactory)->isStackEmpty('my-stack'));
    }

    public function testStackIsNotEmptyWithPushedContent()
    {
        $object = new FakeViewFactory;
        $object->startPush('my-stack', 'some pushed content');

        $this->assertFalse($object->isStackEmpty('my-stack'));
    }

    public function testStackIsNotEmptyWithPrependedContent()
    {
        $object = new FakeViewFactory;
        $object->startPrepend('my-stack', 'some prepended content');

        $this->assertFalse($object->isStackEmpty('my-stack'));
    }
}

class FakeViewFactory
{
    use ManagesStacks;

    protected int $renderCount = 0;
}
