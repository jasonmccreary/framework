<?php

namespace Illuminate\Support\Facades;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\Fake;
use Illuminate\Support\Uri;
use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use RuntimeException;

abstract class Facade
{
    /**
     * The application instance being facaded.
     *
     * @var \Illuminate\Contracts\Foundation\Application|null
     */
    protected static $app;

    /**
     * The resolved object instances.
     *
     * @var array
     */
    protected static $resolvedInstance;

    /**
     * Indicates if the resolved instance should be cached.
     *
     * @var bool
     */
    protected static $cached = true;

    /**
     * Run a Closure when the facade has been resolved.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function resolved(Closure $callback)
    {
        $accessor = static::getFacadeAccessor();

        if (static::$app->resolved($accessor) === true) {
            $callback(static::getFacadeRoot(), static::$app);
        }

        static::$app->afterResolving($accessor, function ($service, $app) use ($callback) {
            $callback($service, $app);
        });
    }

    /**
     * Convert the facade into a Double spy.
     *
     * @return \JMac\Testing\DoubleInterface
     */
    public static function spy()
    {
        if (! static::isMock()) {
            return static::createFreshMockInstance();
        }
    }

    /**
     * Initiate a partial mock on the facade.
     *
     * @return \JMac\Testing\DoubleInterface
     */
    public static function partialMock()
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->passthru();
    }

    /**
     * Initiate a mock expectation on the facade.
     *
     * @return \JMac\Testing\Engine\MethodExpectation
     */
    public static function shouldReceive()
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->allows(...func_get_args());
    }

    /**
     * Initiate a mock expectation on the facade.
     *
     * @return \JMac\Testing\Engine\MethodExpectation
     */
    public static function expects()
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->expects(...func_get_args());
    }

    /**
     * Assert the facade received a call to the given method.
     *
     * @param  string  $method
     * @return \JMac\Testing\Engine\ReceivedAssertion
     */
    public static function shouldHaveReceived($method)
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->received($method);
    }

    /**
     * Assert the facade did not receive a call to the given method.
     *
     * @param  string  $method
     * @return \JMac\Testing\Engine\ReceivedAssertion
     */
    public static function shouldNotHaveReceived($method)
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->received($method)->never();
    }

    /**
     * Create a fresh mock instance for the given class.
     *
     * @return \JMac\Testing\DoubleInterface
     */
    protected static function createFreshMockInstance()
    {
        return tap(static::createMock(), function ($mock) {
            static::swap($mock);
        });
    }

    /**
     * Create a fresh mock instance for the given class.
     *
     * @return \JMac\Testing\DoubleInterface
     */
    protected static function createMock()
    {
        $class = static::getMockableClass();

        if (! $class) {
            throw new RuntimeException('Cannot create a double for a facade with no resolvable class.');
        }

        return Double::for(static::getFacadeRoot() ?? $class, override: true);
    }

    /**
     * Determines whether a mock is set as the instance of the facade.
     *
     * @return bool
     */
    protected static function isMock()
    {
        $name = static::getFacadeAccessor();

        if (! isset(static::$resolvedInstance[$name]) && static::$app) {
            // A prior clearResolvedInstances() call (e.g. Queue\Worker resets
            // this between jobs) only clears this facade-level cache — it
            // never touches the container binding swap() also wrote to. If
            // that binding is still a double, re-adopt it here instead of
            // treating the facade as unmocked and trying to double it again,
            // which would fail since a double's own generated class is final.
            $current = static::$app[$name] ?? null;

            if ($current instanceof DoubleInterface) {
                static::$resolvedInstance[$name] = $current;
            }
        }

        return isset(static::$resolvedInstance[$name]) &&
               static::$resolvedInstance[$name] instanceof DoubleInterface;
    }

    /**
     * Get the mockable class for the bound instance.
     *
     * @return string|null
     */
    protected static function getMockableClass()
    {
        if ($root = static::getFacadeRoot()) {
            return get_class($root);
        }
    }

    /**
     * Hotswap the underlying instance behind the facade.
     *
     * @param  mixed  $instance
     * @return void
     */
    public static function swap($instance)
    {
        static::$resolvedInstance[static::getFacadeAccessor()] = $instance;

        if (isset(static::$app)) {
            static::$app->instance(
                static::getFacadeAccessor(),
                $instance instanceof DoubleInterface ? $instance->instance() : $instance
            );
        }
    }

    /**
     * Determines whether a "fake" has been set as the facade instance.
     *
     * @return bool
     */
    public static function isFake()
    {
        $name = static::getFacadeAccessor();

        return isset(static::$resolvedInstance[$name]) &&
               static::$resolvedInstance[$name] instanceof Fake;
    }

    /**
     * Get the root object behind the facade.
     *
     * @return mixed
     */
    public static function getFacadeRoot()
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor()
    {
        throw new RuntimeException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param  string  $name
     * @return mixed
     */
    protected static function resolveFacadeInstance($name)
    {
        if (isset(static::$resolvedInstance[$name])) {
            $instance = static::$resolvedInstance[$name];

            return $instance instanceof DoubleInterface ? $instance->instance() : $instance;
        }

        if (static::$app) {
            if (static::$cached) {
                return static::$resolvedInstance[$name] = static::$app[$name];
            }

            return static::$app[$name];
        }
    }

    /**
     * Clear a resolved facade instance.
     *
     * @param  ?string  $name
     * @return void
     */
    public static function clearResolvedInstance($name = null)
    {
        unset(static::$resolvedInstance[$name ?? static::getFacadeAccessor()]);
    }

    /**
     * Clear all of the resolved instances.
     *
     * @return void
     */
    public static function clearResolvedInstances()
    {
        static::$resolvedInstance = [];
    }

    /**
     * Get the application default aliases.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function defaultAliases()
    {
        return new Collection([
            'App' => App::class,
            'Arr' => Arr::class,
            'Artisan' => Artisan::class,
            'Auth' => Auth::class,
            'Benchmark' => Benchmark::class,
            'Blade' => Blade::class,
            'Broadcast' => Broadcast::class,
            'Bus' => Bus::class,
            'Cache' => Cache::class,
            'Concurrency' => Concurrency::class,
            'Config' => Config::class,
            'Context' => Context::class,
            'Cookie' => Cookie::class,
            'Crypt' => Crypt::class,
            'Date' => Date::class,
            'DB' => DB::class,
            'Eloquent' => Model::class,
            'Event' => Event::class,
            'File' => File::class,
            'Gate' => Gate::class,
            'Hash' => Hash::class,
            'Http' => Http::class,
            'Image' => Image::class,
            'Js' => Js::class,
            'Lang' => Lang::class,
            'Log' => Log::class,
            'Mail' => Mail::class,
            'Notification' => Notification::class,
            'Number' => Number::class,
            'Password' => Password::class,
            'Process' => Process::class,
            'Queue' => Queue::class,
            'RateLimiter' => RateLimiter::class,
            'Redirect' => Redirect::class,
            'Request' => Request::class,
            'Response' => Response::class,
            'Route' => Route::class,
            'Schedule' => Schedule::class,
            'Schema' => Schema::class,
            'Session' => Session::class,
            'Storage' => Storage::class,
            'Str' => Str::class,
            'Uri' => Uri::class,
            'URL' => URL::class,
            'Validator' => Validator::class,
            'View' => View::class,
            'Vite' => Vite::class,
        ]);
    }

    /**
     * Get the application instance behind the facade.
     *
     * @return \Illuminate\Contracts\Foundation\Application|null
     */
    public static function getFacadeApplication()
    {
        return static::$app;
    }

    /**
     * Set the application instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application|null  $app
     * @return void
     */
    public static function setFacadeApplication($app)
    {
        static::$app = $app;
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }
}
