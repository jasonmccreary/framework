<?php

namespace Illuminate\Tests\Translation;

use JMac\Testing\TestDouble;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use PHPUnit\Framework\TestCase;

class TranslationFileLoaderTest extends TestCase
{
    public function testLoadMethodLoadsTranslationsFromAddedPath()
    {
        $files = TestDouble::for(Filesystem::class);
        $loader = new FileLoader($files, __DIR__);
        $loader->addPath(__DIR__.'/another');

        $files->expects('exists')->with(__DIR__.'/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->returns(['foo' => 'bar']);

        $files->expects('exists')->with(__DIR__.'/another/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/another/en/messages.php')->returns(['baz' => 'backagesplash']);

        $this->assertEquals(['foo' => 'bar', 'baz' => 'backagesplash'], $loader->load('en', 'messages'));
    }

    public function testLoadMethodHandlesMissingAddedPath()
    {
        $files = TestDouble::for(Filesystem::class);
        $loader = new FileLoader($files, __DIR__);
        $loader->addPath(__DIR__.'/missing');

        $files->expects('exists')->with(__DIR__.'/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->returns(['foo' => 'bar']);

        $files->expects('exists')->with(__DIR__.'/missing/en/messages.php')->returns(false);

        $this->assertEquals(['foo' => 'bar'], $loader->load('en', 'messages'));
    }

    public function testLoadMethodOverwritesExistingKeysFromAddedPath()
    {
        $files = TestDouble::for(Filesystem::class);
        $loader = new FileLoader($files, __DIR__);
        $loader->addPath(__DIR__.'/another');

        $files->expects('exists')->with(__DIR__.'/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->returns(['foo' => 'bar']);

        $files->expects('exists')->with(__DIR__.'/another/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/another/en/messages.php')->returns(['foo' => 'baz']);

        $this->assertEquals(['foo' => 'baz'], $loader->load('en', 'messages'));
    }

    public function testLoadMethodLoadsTranslationsFromMultipleAddedPaths()
    {
        $files = TestDouble::for(Filesystem::class);
        $loader = new FileLoader($files, __DIR__);
        $loader->addPath(__DIR__.'/another');
        $loader->addPath(__DIR__.'/yet-another');

        $files->expects('exists')->with(__DIR__.'/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->returns(['foo' => 'bar']);

        $files->expects('exists')->with(__DIR__.'/another/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/another/en/messages.php')->returns(['baz' => 'backagesplash']);

        $files->expects('exists')->with(__DIR__.'/yet-another/en/messages.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/yet-another/en/messages.php')->returns(['qux' => 'quux']);

        $this->assertEquals(['foo' => 'bar', 'baz' => 'backagesplash', 'qux' => 'quux'], $loader->load('en', 'messages'));
    }

    public function testLoadMethodWithoutNamespacesProperlyCallsLoader()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $files->expects('exists')->with(__DIR__.'/en/foo.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/en/foo.php')->returns(['messages']);

        $this->assertEquals(['messages'], $loader->load('en', 'foo', null));
    }

    public function testLoadMethodWithoutNamespacesProperlyCallsLoaderWithMultiplePaths()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), [__DIR__, __DIR__.'/second']);
        $files->expects('exists')->with(__DIR__.'/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/second/en/foo.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/en/foo.php')->returns(['messages' => 'first']);
        $files->expects('getRequire')->with(__DIR__.'/second/en/foo.php')->returns(['messages' => 'second']);

        $this->assertEquals(['messages' => 'second'], $loader->load('en', 'foo', null));
    }

    public function testLoadMethodWithNamespacesProperlyCallsLoader()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $files->expects('exists')->with('bar/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(false);
        $files->expects('getRequire')->with('bar/en/foo.php')->returns(['foo' => 'bar']);
        $loader->addNamespace('namespace', 'bar');

        $this->assertEquals(['foo' => 'bar'], $loader->load('en', 'foo', 'namespace'));
    }

    public function testLoadMethodWithNamespacesProperlyCallsLoaderWithMultiplePaths()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), [__DIR__, __DIR__.'/second']);
        $files->expects('exists')->with('test-namespace-dir/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(false);
        $files->expects('exists')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->returns(false);
        $files->expects('getRequire')->with('test-namespace-dir/en/foo.php')->returns(['foo' => 'bar']);
        $loader->addNamespace('namespace', 'test-namespace-dir');

        $this->assertEquals(['foo' => 'bar'], $loader->load('en', 'foo', 'namespace'));
    }

    public function testLoadMethodWithNamespacesProperlyCallsLoaderAndLoadsLocalOverrides()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $files->expects('exists')->with('bar/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(true);
        $files->expects('getRequire')->with('bar/en/foo.php')->returns(['foo' => 'bar']);
        $files->expects('getRequire')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(['foo' => 'override', 'baz' => 'boom']);
        $loader->addNamespace('namespace', 'bar');

        $this->assertEquals(['foo' => 'override', 'baz' => 'boom'], $loader->load('en', 'foo', 'namespace'));
    }

    public function testLoadMethodWithNamespacesProperlyCallsLoaderAndLoadsLocalOverridesWithMultiplePaths()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), [__DIR__, __DIR__.'/second']);
        $files->expects('exists')->with('test-namespace-dir/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->returns(true);
        $files->expects('getRequire')->with('test-namespace-dir/en/foo.php')->returns(['foo' => 'bar']);
        $files->expects('getRequire')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(['foo' => 'override', 'baz' => 'boom']);
        $files->expects('getRequire')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->returns(['foo' => 'override-2', 'baz' => 'boom-2']);
        $loader->addNamespace('namespace', 'test-namespace-dir');

        $this->assertEquals(['foo' => 'override-2', 'baz' => 'boom-2'], $loader->load('en', 'foo', 'namespace'));
    }

    public function testLoadMethodWithNamespacesProperlyCallsLoaderAndLoadsLocalOverridesWithMultiplePathsWithMissingKey()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), [__DIR__, __DIR__.'/second']);
        $files->expects('exists')->with('test-namespace-dir/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(true);
        $files->expects('exists')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->returns(true);
        $files->expects('getRequire')->with('test-namespace-dir/en/foo.php')->returns(['foo' => 'bar']);
        $files->expects('getRequire')->with(__DIR__.'/vendor/namespace/en/foo.php')->returns(['foo' => 'override', 'baz' => 'boom']);
        $files->expects('getRequire')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->returns(['baz' => 'boom-2']);
        $loader->addNamespace('namespace', 'test-namespace-dir');

        $this->assertEquals(['foo' => 'override', 'baz' => 'boom-2'], $loader->load('en', 'foo', 'namespace'));
    }

    public function testEmptyArraysReturnedWhenFilesDontExist()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $files->expects('exists')->with(__DIR__.'/en/foo.php')->returns(false);
        $files->expects('getRequire')->never();

        $this->assertSame([], $loader->load('en', 'foo', null));
    }

    public function testEmptyArraysReturnedWhenFilesDontExistForNamespacedItems()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $files->expects('getRequire')->never();

        $this->assertSame([], $loader->load('en', 'foo', 'bar'));
    }

    public function testLoadMethodForJSONProperlyCallsLoader()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $files->expects('exists')->with(__DIR__.'/en.json')->returns(true);
        $files->expects('get')->with(__DIR__.'/en.json')->returns('{"foo":"bar"}');

        $this->assertEquals(['foo' => 'bar'], $loader->load('en', '*', '*'));
    }

    public function testLoadMethodForJSONProperlyCallsLoaderForMultiplePaths()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $loader->addJsonPath(__DIR__.'/another');

        $files->expects('exists')->with(__DIR__.'/en.json')->returns(true);
        $files->expects('exists')->with(__DIR__.'/another/en.json')->returns(true);
        $files->expects('get')->with(__DIR__.'/en.json')->returns('{"foo":"bar"}');
        $files->expects('get')->with(__DIR__.'/another/en.json')->returns('{"foo":"backagebar", "baz": "backagesplash"}');

        $this->assertEquals(['foo' => 'bar', 'baz' => 'backagesplash'], $loader->load('en', '*', '*'));
    }

    public function testLoadMethodThrowExceptionWhenProvideInvalidJSON()
    {
        $loader = new FileLoader($files = TestDouble::for(Filesystem::class), __DIR__);
        $loader->addJsonPath(__DIR__.'/invalid');

        $invalidJsonString = '.{"foo":"cricket", "baz": "football"}';
        $files->expects('exists')->with(__DIR__.'/invalid/en.json')->returns(true);
        $files->expects('get')->with(__DIR__.'/invalid/en.json')->returns($invalidJsonString);

        $this->expectException(\RuntimeException::class);
        $loader->load('en', '*', '*');
    }

    public function testAllRegisteredNamespaceReturnProperly()
    {
        $loader = new FileLoader(TestDouble::for(Filesystem::class), __DIR__);
        $loader->addNamespace('namespace', 'foo');
        $loader->addNamespace('namespace2', 'bar');
        $this->assertEquals(['namespace' => 'foo', 'namespace2' => 'bar'], $loader->namespaces());
    }

    public function testAllAddedJsonPathsReturnProperly()
    {
        $loader = new FileLoader(TestDouble::for(Filesystem::class), __DIR__);
        $path1 = __DIR__.'/another';
        $path2 = __DIR__.'/another2';
        $loader->addJsonPath($path1);
        $loader->addJsonPath($path2);
        $this->assertEquals([$path1, $path2], $loader->jsonPaths());
    }

    public function testAllAddedPathsReturnProperly()
    {
        $loader = new FileLoader(TestDouble::for(Filesystem::class), __DIR__);
        $path1 = __DIR__.'/another';
        $path2 = __DIR__.'/another2';
        $loader->addPath($path1);
        $loader->addPath($path2);
        $this->assertEquals([$path1, $path2], array_slice($loader->paths(), 1));
    }
}
