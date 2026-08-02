<?php

namespace Illuminate\Tests\Foundation;

use Exception;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Support\ServiceProvider;
use JMac\Testing\TestDouble;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use stdClass;

class FoundationProviderRepositoryTest extends TestCase
{
    public function testServicesAreRegisteredWhenManifestIsNotRecompiled()
    {
        $app = TestDouble::for(Application::class);

        // TODO: no direct TestDouble equivalent — this is a true partial mock (real
        // load() must run and internally call the stubbed loadManifest()/
        // shouldRecompile() on the *same* object); TestDouble::for()'s passthru mode
        // only delegates to a separate real instance, so self-calls never route back
        // through the double.
        $repo = m::mock(ProviderRepository::class.'[createProvider,loadManifest,shouldRecompile]', [$app, TestDouble::for(Filesystem::class), [__DIR__.'/services.php']]);
        $repo->shouldReceive('loadManifest')->andReturn(['eager' => ['foo'], 'deferred' => ['deferred'], 'providers' => ['providers'], 'when' => []]);
        $repo->shouldReceive('shouldRecompile')->andReturn(false);

        $app->expects('register')->with('foo');
        $app->allows('runningInConsole')->returns(false);
        $app->expects('addDeferredServices')->with(['deferred']);

        $repo->load([]);
    }

    public function testManifestIsProperlyRecompiled()
    {
        $app = TestDouble::for(Application::class);

        // TODO: no direct TestDouble equivalent — this is a true partial mock (real
        // load() must run and internally call the stubbed loadManifest()/
        // writeManifest()/shouldRecompile() on the *same* object); TestDouble::for()'s
        // passthru mode only delegates to a separate real instance, so self-calls
        // never route back through the double.
        $repo = m::mock(ProviderRepository::class.'[createProvider,loadManifest,writeManifest,shouldRecompile]', [$app, TestDouble::for(Filesystem::class), [__DIR__.'/services.php']]);

        $repo->shouldReceive('loadManifest')->andReturn(['eager' => [], 'deferred' => ['deferred']]);
        $repo->shouldReceive('shouldRecompile')->andReturn(true);

        // foo mock is just a deferred provider
        $repo->shouldReceive('createProvider')->with('foo')->andReturn($fooMock = TestDouble::for(stdClass::class));
        $fooMock->expects('isDeferred')->returns(true);
        $fooMock->expects('provides')->returns(['foo.provides1', 'foo.provides2']);
        $fooMock->expects('when')->returns([]);

        // bar mock is added to eagers since it's not reserved
        $repo->shouldReceive('createProvider')->with('bar')->andReturn($barMock = TestDouble::for(ServiceProvider::class));
        $barMock->expects('isDeferred')->returns(false);
        $repo->shouldReceive('writeManifest')->andReturnUsing(function ($manifest) {
            return $manifest;
        });

        $app->expects('register')->with('bar');
        $app->allows('runningInConsole')->returns(false);
        $app->expects('addDeferredServices')->with(['foo.provides1' => 'foo', 'foo.provides2' => 'foo']);

        $repo->load(['foo', 'bar']);
    }

    public function testShouldRecompileReturnsCorrectValue()
    {
        $repo = new ProviderRepository(TestDouble::for(ApplicationContract::class), new Filesystem, __DIR__.'/services.php');
        $this->assertTrue($repo->shouldRecompile(null, []));
        $this->assertTrue($repo->shouldRecompile(['providers' => ['foo']], ['foo', 'bar']));
        $this->assertFalse($repo->shouldRecompile(['providers' => ['foo']], ['foo']));
    }

    public function testLoadManifestReturnsParsedJSON()
    {
        $repo = new ProviderRepository(TestDouble::for(ApplicationContract::class), $files = TestDouble::for(Filesystem::class), __DIR__.'/services.php');
        $files->expects('exists')->with(__DIR__.'/services.php')->returns(true);
        $files->expects('getRequire')->with(__DIR__.'/services.php')->returns($array = ['users' => ['dayle' => true], 'when' => []]);

        $this->assertEquals($array, $repo->loadManifest());
    }

    public function testWriteManifestStoresToProperLocation()
    {
        $repo = new ProviderRepository(TestDouble::for(ApplicationContract::class), $files = TestDouble::for(Filesystem::class), __DIR__.'/services.php');
        $files->expects('replace')->with(__DIR__.'/services.php', '<?php return '.var_export(['foo'], true).';');

        $result = $repo->writeManifest(['foo']);

        $this->assertEquals(['foo', 'when' => []], $result);
    }

    public function testWriteManifestThrowsExceptionIfManifestDirDoesntExist()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/^The (.*) directory must be present and writable.$/');

        $repo = new ProviderRepository(TestDouble::for(ApplicationContract::class), $files = TestDouble::for(Filesystem::class), __DIR__.'/cache/services.php');
        $files->expects('replace')->never();

        $repo->writeManifest(['foo']);
    }
}
