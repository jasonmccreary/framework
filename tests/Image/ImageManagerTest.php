<?php

namespace Illuminate\Tests\Image;

use JMac\Testing\TestDouble;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Image\Driver;
use Illuminate\Contracts\Image\Transformation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image;
use Illuminate\Image\ImageException;
use Illuminate\Image\ImageManager;
use Illuminate\Image\ImagePipeline;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ImageManagerTest extends TestCase
{
    public function test_default_driver_returns_configured_value()
    {
        $app = $this->makeApp(['images.default' => 'imagick']);

        $manager = new ImageManager($app);

        $this->assertSame('imagick', $manager->getDefaultDriver());
    }

    public function test_default_driver_falls_back_to_gd()
    {
        $app = $this->makeApp([]);

        $manager = new ImageManager($app);

        $this->assertSame('gd', $manager->getDefaultDriver());
    }

    public function test_extend_registers_custom_driver()
    {
        $app = $this->makeApp(['images.default' => 'custom']);

        $mockDriver = TestDouble::for(Driver::class);

        $manager = new ImageManager($app);
        $manager->extend('custom', function ($app) use ($mockDriver) {
            return $mockDriver;
        });

        $this->assertSame($mockDriver, $manager->driver('custom'));
    }

    public function test_driver_caches_resolved_instances()
    {
        $app = $this->makeApp([]);

        $mockDriver = TestDouble::for(Driver::class);

        $manager = new ImageManager($app);
        $manager->extend('custom', function () use ($mockDriver) {
            return $mockDriver;
        });

        $first = $manager->driver('custom');
        $second = $manager->driver('custom');

        $this->assertSame($first, $second);
    }

    public function test_throws_for_unsupported_driver()
    {
        $app = $this->makeApp([]);

        $manager = new ImageManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image driver [nonexistent] is not supported.');

        $manager->driver('nonexistent');
    }

    public function test_from_bytes_returns_image_with_contents()
    {
        $app = $this->makeApp([]);
        $manager = new ImageManager($app);

        $contents = $this->fakeImageContents();
        $image = $manager->fromBytes($contents);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame($contents, $image->toBytes());
    }

    public function test_from_path_returns_image_from_file_path()
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->getRealPath();

        $filesystem = TestDouble::for(Filesystem::class);
        $filesystem->expects('get')->with($path)->returns(file_get_contents($path));

        $app = $this->makeApp([]);
        $app->allows('make')->with(Filesystem::class)->returns($filesystem);

        $manager = new ImageManager($app);
        $image = $manager->fromPath($path);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertNotEmpty($image->toBytes());
    }

    public function test_from_path_is_lazy()
    {
        $filesystem = TestDouble::for(Filesystem::class);
        $filesystem->expects('get')->never();

        $app = $this->makeApp([]);
        $app->allows('make')->with(Filesystem::class)->returns($filesystem);

        $manager = new ImageManager($app);
        $image = $manager->fromPath('/some/path.jpg');

        $this->assertInstanceOf(Image::class, $image);
    }

    public function test_from_storage_returns_image_from_storage_disk_path()
    {
        $contents = $this->fakeImageContents();

        $disk = TestDouble::for(\stdClass::class);
        $disk->expects('get')->with('images/avatar.jpg')->returns($contents);

        $filesystem = TestDouble::for(FilesystemFactory::class);
        $filesystem->expects('disk')->with('public')->returns($disk);

        $app = $this->makeApp([]);
        $app->allows('make')->with(FilesystemFactory::class)->returns($filesystem);

        $manager = new ImageManager($app);
        $image = $manager->fromStorage('images/avatar.jpg', 'public');

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame($contents, $image->toBytes());
    }

    public function test_from_storage_is_lazy()
    {
        $filesystem = TestDouble::for(FilesystemFactory::class);
        $filesystem->expects('disk')->never();

        $app = $this->makeApp([]);
        $app->allows('make')->with(FilesystemFactory::class)->returns($filesystem);

        $manager = new ImageManager($app);
        $image = $manager->fromStorage('images/avatar.jpg', 'public');

        $this->assertInstanceOf(Image::class, $image);
    }

    public function test_from_upload_returns_image_from_uploaded_file()
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $app = $this->makeApp([]);
        $manager = new ImageManager($app);
        $image = $manager->fromUpload($file);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame(file_get_contents($file->getRealPath()), $image->toBytes());
        $this->assertSame($file, $image->file());
    }

    public function test_from_url_returns_image()
    {
        $contents = $this->fakeImageContents();

        $http = TestDouble::for(HttpFactory::class);
        $response = TestDouble::for(\stdClass::class);
        $response->allows('body')->returns($contents);
        $http->allows('get')->with('https://example.com/photo.jpg')->returns($response);

        $app = $this->makeApp([]);
        $app->allows('make')->with(HttpFactory::class)->returns($http);

        $manager = new ImageManager($app);
        $image = $manager->fromUrl('https://example.com/photo.jpg');

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame($contents, $image->toBytes());
    }

    public function test_from_url_is_lazy()
    {
        $http = TestDouble::for(HttpFactory::class);
        $http->expects('get')->never();

        $app = $this->makeApp([]);
        $app->allows('make')->with(HttpFactory::class)->returns($http);

        $manager = new ImageManager($app);
        $image = $manager->fromUrl('https://example.com/photo.jpg');

        $this->assertInstanceOf(Image::class, $image);
    }

    public function test_from_base64_returns_image()
    {
        $contents = $this->fakeImageContents();
        $base64 = base64_encode($contents);

        $app = $this->makeApp([]);
        $manager = new ImageManager($app);

        $image = $manager->fromBase64($base64);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame($contents, $image->toBytes());
    }

    public function test_from_base64_throws_for_invalid_data()
    {
        $app = $this->makeApp([]);
        $manager = new ImageManager($app);

        $this->expectException(ImageException::class);
        $this->expectExceptionMessage('Invalid base64 image data.');

        $manager->fromBase64('!!!not-base64!!!')->toBytes();
    }

    public function test_extend_overwrites_previous_registration()
    {
        $app = $this->makeApp([]);

        $firstDriver = TestDouble::for(Driver::class);
        $secondDriver = TestDouble::for(Driver::class);

        $manager = new ImageManager($app);
        $manager->extend('custom', fn () => $firstDriver);
        $manager->extend('custom', fn () => $secondDriver);

        $this->assertSame($secondDriver, $manager->driver('custom'));
    }

    public function test_driver_caches_separately_by_name()
    {
        $app = $this->makeApp([]);

        $driver1 = TestDouble::for(Driver::class);
        $driver2 = TestDouble::for(Driver::class);

        $manager = new ImageManager($app);
        $manager->extend('one', fn () => $driver1);
        $manager->extend('two', fn () => $driver2);

        $this->assertSame($driver1, $manager->driver('one'));
        $this->assertSame($driver2, $manager->driver('two'));
        $this->assertNotSame($manager->driver('one'), $manager->driver('two'));
    }

    public function test_transform_using_applies_handlers_to_new_driver_instances()
    {
        $app = $this->makeApp([]);
        $driver = new class implements Driver
        {
            public array $handlers = [];

            public function process(string $contents, ImagePipeline $pipeline): string
            {
                return $contents;
            }

            public function transformUsing(string $transformation, callable $callback): static
            {
                $this->handlers[$transformation] = $callback;

                return $this;
            }
        };
        $transformation = new class implements Transformation {
            //
        };
        $callback = fn () => null;

        $manager = new ImageManager($app);
        $manager->extend('custom', fn () => $driver);
        $manager->transformUsing('custom', $transformation::class, $callback);

        $this->assertSame($callback, $manager->driver('custom')->handlers[$transformation::class]);
    }

    public function test_transform_using_applies_handlers_to_resolved_driver_instances()
    {
        $app = $this->makeApp([]);
        $driver = new class implements Driver
        {
            public array $handlers = [];

            public function process(string $contents, ImagePipeline $pipeline): string
            {
                return $contents;
            }

            public function transformUsing(string $transformation, callable $callback): static
            {
                $this->handlers[$transformation] = $callback;

                return $this;
            }
        };
        $transformation = new class implements Transformation {
            //
        };
        $callback = fn () => null;

        $manager = new ImageManager($app);
        $manager->extend('custom', fn () => $driver);
        $manager->driver('custom');
        $manager->transformUsing('custom', $transformation::class, $callback);

        $this->assertSame($callback, $driver->handlers[$transformation::class]);
    }

    protected function fakeImageContents(): string
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        return file_get_contents($file->getRealPath());
    }

    protected function makeApp(array $config): Application
    {
        $app = TestDouble::for(Application::class, \ArrayAccess::class);

        $configRepo = new Repository($config);

        $app->allows('make')->with('config')->returns($configRepo);
        $app->allows('offsetGet')->with('config')->returns($configRepo);
        $app->allows('offsetExists')->returns(true);

        return $app;
    }
}
