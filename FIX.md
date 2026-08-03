# Shift: unhandled `Mockery::mock($existingInstance)->makePartial()` case

Full commit with this fix (and the two cases that genuinely needed manual conversion, for contrast):
https://github.com/jasonmccreary/framework/commit/fc319f0891

## Context

Shift's Mockery → Test Double converter rebuilds `Mockery::mock(Foo::class, [$args])->makePartial()`
constructs as `TestDouble::for(Foo::class)->passthru(new Foo($args))`. It currently bails out (leaving
the call for manual conversion) whenever it can't confidently rebuild the construction — e.g. a
`namedMock()` label, more than one target class/interface, or constructor arguments that aren't a
plain array.

One of the flagged "manual conversion" cases in our test suite didn't actually need any of that
judgment. It was mocking an **already-constructed instance** pulled from the container, not a
class-string with constructor args:

```php
Mockery::mock(app('blade.compiler'))->makePartial();
```

There's no constructor-arg array to reshape here at all — the instance already exists. The direct
equivalent in Test Double is:

```php
TestDouble::for($existingInstance)->passthru();
```

`TestDouble::for()` accepts an object directly (remembering it as the "known instance"), and a
no-arg `->passthru()` call falls back to that known instance automatically. This is arguably a
*simpler* case than the class-string + constructor-args case Shift already handles mechanically,
since there's no argument list to rebuild.

## Before / after

**Before** (`tests/Integration/View/BladeTest.php`):

```php
use Mockery;

// ...

public function test_view_cache_command_deduplicates_paths_before_compiling()
{
    View::addNamespace('templates', join_paths(__DIR__, 'templates'));
    View::addNamespace('components', join_paths(__DIR__, 'templates', 'components'));

    $compiler = Mockery::mock(app('blade.compiler'))->makePartial();
    $compiler->expects('compile')->with(realpath(__DIR__.'/templates/components/panel.blade.php'));

    $this->instance('blade.compiler', $compiler);

    $this->artisan('view:cache');
}
```

**After:**

```php
use JMac\Testing\TestDouble;

// ...

public function test_view_cache_command_deduplicates_paths_before_compiling()
{
    View::addNamespace('templates', join_paths(__DIR__, 'templates'));
    View::addNamespace('components', join_paths(__DIR__, 'templates', 'components'));

    $compiler = TestDouble::for(app('blade.compiler'))->passthru();
    $compiler->expects('compile')->with(realpath(__DIR__.'/templates/components/panel.blade.php'));

    $this->instance('blade.compiler', $compiler);

    $this->artisan('view:cache');
}
```

## Suggested rule addition

When the argument to `Mockery::mock(...)` is an **expression that isn't a class-string literal**
(i.e. not `Foo::class` / `'Foo'`) — e.g. a variable, a function call like `app(...)`, `resolve(...)`,
`$this->app->make(...)`, or any other existing-object expression — and there's no second
constructor-args argument, rebuild it as:

```php
TestDouble::for(<same expression>)->passthru()
```

This is distinct from the cases that should still be left for manual review (named mocks, multiple
targets, non-array constructor args) — those genuinely require judgment about what the real
constructor call should look like. Mocking an existing instance never does; the instance already
exists, so there's no construction call to rebuild in the first place.
