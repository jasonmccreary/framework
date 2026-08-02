<?php

namespace Illuminate\Tests\Foundation;

use JMac\Testing\TestDouble;
use Exception;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class FoundationProviderRepositoryTest extends TestCase
{
    public function testServicesAreRegisteredWhenManifestIsNotRecompiled()
    {
        $app = TestDouble::for(Application::class);

        $repo = m::mock(ProviderRepository::class.'[createProvider,loadManifest,shouldRecompile]', [$app, TestDouble::for(Filesystem::class), [__DIR__.'/services.php']]);
        $repo->expects('loadManifest')->returns(['eager' => ['foo'], 'deferred' => ['deferred'], 'providers' => ['providers'], 'when' => []]);
        $repo->expects('shouldRecompile')->returns(false);

        $app->expects('register')->with('foo');
        $app->allows('runningInConsole')->returns(false);
        $app->expects('addDeferredServices')->with(['deferred']);

        $repo->load([]);
    }

    public function testManifestIsProperlyRecompiled()
    {
        $app = TestDouble::for(Application::class);

        $repo = m::mock(ProviderRepository::class.'[createProvider,loadManifest,writeManifest,shouldRecompile]', [$app, TestDouble::for(Filesystem::class), [__DIR__.'/services.php']]);

        $repo->expects('loadManifest')->returns(['eager' => [], 'deferred' => ['deferred']]);
        $repo->expects('shouldRecompile')->returns(true);

        // foo mock is just a deferred provider
        $repo->expects('createProvider')->with('foo')->returns($fooMock = TestDouble::for(stdClass::class));
        $fooMock->expects('isDeferred')->returns(true);
        $fooMock->expects('provides')->returns(['foo.provides1', 'foo.provides2']);
        $fooMock->expects('when')->returns([]);

        // bar mock is added to eagers since it's not reserved
        $repo->expects('createProvider')->with('bar')->returns($barMock = TestDouble::for(ServiceProvider::class));
        $barMock->expects('isDeferred')->returns(false);
        $repo->expects('writeManifest')->resolves(function ($manifest) {
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
