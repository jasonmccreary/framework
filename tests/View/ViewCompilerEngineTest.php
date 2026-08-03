<?php

namespace Illuminate\Tests\View;

use JMac\Testing\TestDouble;
use ErrorException;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\ViewException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ViewCompilerEngineTest extends TestCase
{
    public function testViewsMayBeRecompiledAndRendered()
    {
        $engine = $this->getEngine();
        $engine->getCompiler()->allows('getCompiledPath')->with(__DIR__.'/fixtures/foo.php')->returns(__DIR__.'/fixtures/basic.php');
        $engine->getCompiler()->expects('isExpired')->with(__DIR__.'/fixtures/foo.php')->returns(true);
        $engine->getCompiler()->expects('compile')->with(__DIR__.'/fixtures/foo.php');
        $results = $engine->get(__DIR__.'/fixtures/foo.php');

        $this->assertSame('Hello World
', $results);
    }

    public function testViewsAreNotRecompiledIfTheyAreNotExpired()
    {
        $engine = $this->getEngine();
        $engine->getCompiler()->allows('getCompiledPath')->with(__DIR__.'/fixtures/foo.php')->returns(__DIR__.'/fixtures/basic.php');
        $engine->getCompiler()->expects('isExpired')->returns(false);
        $engine->getCompiler()->expects('compile')->never();
        $results = $engine->get(__DIR__.'/fixtures/foo.php');

        $this->assertSame('Hello World
', $results);
    }

    public function testRegularExceptionsAreReThrownAsViewExceptions()
    {
        $engine = $this->getEngine();
        $engine->getCompiler()->allows('getCompiledPath')->with(__DIR__.'/fixtures/foo.php')->returns(__DIR__.'/fixtures/regular-exception.php');
        $engine->getCompiler()->expects('isExpired')->returns(false);

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('regular exception message');

        $engine->get(__DIR__.'/fixtures/foo.php');
    }

    public function testHttpExceptionsAreNotReThrownAsViewExceptions()
    {
        $engine = $this->getEngine();
        $engine->getCompiler()->allows('getCompiledPath')->with(__DIR__.'/fixtures/foo.php')->returns(__DIR__.'/fixtures/http-exception.php');
        $engine->getCompiler()->expects('isExpired')->returns(false);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('http exception message');

        $engine->get(__DIR__.'/fixtures/foo.php');
    }

    public function testThatViewsAreNotAskTwiceIfTheyAreExpired()
    {
        $engine = $this->getEngine();
        $engine->getCompiler()->allows('getCompiledPath')->with(__DIR__.'/fixtures/foo.php')->returns(__DIR__.'/fixtures/basic.php');
        $engine->getCompiler()->shouldReceive('isExpired')->twice()->andReturn(false);
        $engine->getCompiler()->expects('compile')->never();

        $engine->get(__DIR__.'/fixtures/foo.php');
        $engine->get(__DIR__.'/fixtures/foo.php');
        $engine->get(__DIR__.'/fixtures/foo.php');

        $engine->forgetCompiledOrNotExpired();

        $engine->get(__DIR__.'/fixtures/foo.php');
    }

    public function testViewsAreRecompiledWhenCompiledViewIsMissingViaFileNotFoundException()
    {
        $compiled = __DIR__.'/fixtures/basic.php';
        $path = __DIR__.'/fixtures/foo.php';

        $files = TestDouble::for(Filesystem::class);
        $engine = $this->getEngine($files);

        $files->expects('getRequire')->with($compiled, [])->returns('compiled-content');

        $files->expects('getRequire')->with($compiled, [])->throws(new FileNotFoundException(
                "File does not exist at path {$path}."
            ));

        $files->expects('getRequire')->with($compiled, [])->returns('compiled-content');

        $engine->getCompiler()->expects('getCompiledPath')->times(3)->with($path)->returns($compiled);

        $engine->getCompiler()->expects('isExpired')->returns(true);

        $engine->getCompiler()
            ->shouldReceive('compile')
            ->twice()
            ->with($path);

        $engine->get($path);
        $engine->get($path);
    }

    public function testViewsAreRecompiledWhenCompiledViewIsMissingViaRequireException()
    {
        $compiled = __DIR__.'/fixtures/basic.php';
        $path = __DIR__.'/fixtures/foo.php';

        $files = TestDouble::for(Filesystem::class);
        $engine = $this->getEngine($files);

        $files->expects('getRequire')->with($compiled, [])->returns('compiled-content');

        $files->expects('getRequire')->with($compiled, [])->throws(new ErrorException(
                "require({$path}): Failed to open stream: No such file or directory",
            ));

        $files->expects('getRequire')->with($compiled, [])->returns('compiled-content');

        $engine->getCompiler()->expects('getCompiledPath')->times(3)->with($path)->returns($compiled);

        $engine->getCompiler()->expects('isExpired')->returns(true);

        $engine->getCompiler()
            ->shouldReceive('compile')
            ->twice()
            ->with($path);

        $engine->get($path);
        $engine->get($path);
    }

    public function testViewsAreRecompiledJustOnceWhenCompiledViewIsMissing()
    {
        $compiled = __DIR__.'/fixtures/basic.php';
        $path = __DIR__.'/fixtures/foo.php';

        $files = TestDouble::for(Filesystem::class);
        $engine = $this->getEngine($files);

        $files->expects('getRequire')->with($compiled, [])->returns('compiled-content');

        $files->expects('getRequire')->with($compiled, [])->throws(new FileNotFoundException(
                "File does not exist at path {$path}."
            ));

        $files->expects('getRequire')->with($compiled, [])->throws(new FileNotFoundException(
                "File does not exist at path {$path}."
            ));

        $engine->getCompiler()->expects('getCompiledPath')->times(3)->with($path)->returns($compiled);

        $engine->getCompiler()->expects('isExpired')->returns(true);

        $engine->getCompiler()
            ->shouldReceive('compile')
            ->twice()
            ->with($path);

        $engine->get($path);

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage("File does not exist at path {$path}.");
        $engine->get($path);
    }

    public function testViewsAreNotRecompiledOnRegularViewException()
    {
        $compiled = __DIR__.'/fixtures/basic.php';
        $path = __DIR__.'/fixtures/foo.php';

        $files = TestDouble::for(Filesystem::class);
        $engine = $this->getEngine($files);

        $files->expects('getRequire')->with($compiled, [])->throws(new Exception(
                'Just an regular error...'
            ));

        $engine->getCompiler()->expects('isExpired')->returns(false);

        $engine->getCompiler()->expects('compile')->never();

        $engine->getCompiler()->expects('getCompiledPath')->with($path)->returns($compiled);

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Just an regular error...');
        $engine->get($path);
    }

    public function testViewsAreNotRecompiledIfTheyWereJustCompiled()
    {
        $compiled = __DIR__.'/fixtures/basic.php';
        $path = __DIR__.'/fixtures/foo.php';

        $files = TestDouble::for(Filesystem::class);
        $engine = $this->getEngine($files);

        $files->expects('getRequire')->with($compiled, [])->throws(new FileNotFoundException(
                "File does not exist at path {$path}."
            ));

        $engine->getCompiler()->expects('isExpired')->returns(true);

        $engine->getCompiler()->expects('compile')->with($path);

        $engine->getCompiler()->expects('getCompiledPath')->with($path)->returns($compiled);

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage("File does not exist at path {$path}.");
        $engine->get($path);
    }

    protected function getEngine($filesystem = null)
    {
        return new CompilerEngine(TestDouble::for(CompilerInterface::class), $filesystem ?: new Filesystem);
    }
}
