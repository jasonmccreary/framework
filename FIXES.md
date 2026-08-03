# Shift: two unhandled Mockery → Test Double shorthand cases

Full commit with these fixes (plus several other cases that needed broader
code context and aren't included here — see note at the bottom):
https://github.com/jasonmccreary/framework/commit/7d9f81775eb2ee2be73c8d73c2c7891b77fe13a0

## Summary

Shift's Mockery → Test Double converter already handles the "expectation
array" shorthand —

```php
Mockery::mock(Foo::class, ['method' => $value])
```

→

```php
TestDouble::for(Foo::class)->allows('method')->returns($value)
```

— when that call is the direct right-hand side of an assignment. Both cases
below are the *same* rule, just applied one level too shallow: it bails as
soon as the `mock()` call isn't sitting at that top level, even though
turning it into a preceding statement is mechanical either way — no
semantic judgment is required, just recursing/hoisting one level further.

Both were left as raw `Mockery::mock(...)` calls in the converted files,
requiring manual follow-up.

## Case 1: expectation-array `mock()` call used as an inline argument

### Context

`Mockery::mock(Class::class, ['method' => $value])` was passed directly as
an argument to another call (a constructor, in every occurrence found)
instead of being assigned to a variable first. The converter's existing
rule only fires when the `mock()` call is the RHS of an assignment, so it
left these as-is.

### Before (`tests/Console/ConsoleApplicationTest.php`)

```php
public function testCallFullyStringCommandLine()
{
    $artisan = new Application(
        m::mock(ApplicationContract::class, ['version' => '6.0']),
        m::mock(Dispatcher::class, ['dispatch' => null]),
        'testing'
    );
```

### After

```php
public function testCallFullyStringCommandLine()
{
    $applicationContract = TestDouble::for(ApplicationContract::class);
    $applicationContract->allows('version')->returns('6.0');

    $dispatcher = TestDouble::for(Dispatcher::class);
    $dispatcher->allows('dispatch')->returns(null);

    $artisan = new Application(
        $applicationContract,
        $dispatcher,
        'testing'
    );
```

Six more occurrences of the exact same one-argument shape (just
`m::mock(Dispatcher::class, ['dispatch' => null])` passed inline) appear
later in the same file, and two more in
`tests/Foundation/Console/RouteListCommandTest.php`. All six convert with
the identical two-line hoist:

```php
$dispatcher = TestDouble::for(Dispatcher::class);
$dispatcher->allows('dispatch')->returns(null);
```

placed immediately before the statement that used to contain the inline
`m::mock(...)` call, with the call site swapped for the new variable.

### Suggested rule addition

When an `expectation-array` `Mockery::mock(...)` call appears as an
argument expression (constructor call, method call, array literal — any
context where it isn't already the sole RHS of `$var = ...`), hoist it:

1. Generate a variable name from the target class (e.g. `Dispatcher` →
   `$dispatcher`; on a collision, fall back to a numbered suffix).
2. Insert `$var = TestDouble::for(Class::class);` followed by one
   `$var->allows('method')->returns($value);` per array entry, immediately
   before the statement containing the original call.
3. Replace the original call site with `$var`.

This is the same transform the converter already performs for the
assignment case — it just needs to trigger for "argument position" in
addition to "assignment RHS position."

## Case 2: expectation-array `mock()` call nested inside another expectation-array value

### Context

The *value* side of an expectation-array entry was itself another
`Mockery::mock(...)` expectation-array call (occasionally two levels
deep), e.g. `['getGrammar' => Mockery::mock(Grammar::class, [...])]`. The
converter apparently only inspects the immediate `mock()` call's own
target/args shape and doesn't recurse into a value that is itself another
`mock()` call, so it left the whole expression as raw Mockery.

### Before (`tests/Database/DatabaseEloquentBelongsToManyWithCastedAttributesTest.php`)

```php
$builder->allows('getQuery')->returns(m::mock(stdClass::class, ['getGrammar' => m::mock(Grammar::class, ['isExpression' => false])]));
```

### After

```php
$grammar = TestDouble::for(Grammar::class);
$grammar->allows('isExpression')->returns(false);

$query = TestDouble::for(stdClass::class);
$query->allows('getGrammar')->returns($grammar);

$builder->allows('getQuery')->returns($query);
```

The same shape (minus the double nesting — the inner value was already a
plain variable) appears in
`tests/Database/DatabaseEloquentBelongsToManyWithDefaultAttributesTest.php`,
`tests/Database/DatabaseEloquentBelongsToManyWithoutTouchingTest.php`, and
`tests/Database/DatabaseEloquentMorphToManyTest.php`:

```php
// Before
$builder->allows('getQuery')->returns(m::mock(stdClass::class, ['getGrammar' => $grammar]));

// After
$query = TestDouble::for(stdClass::class);
$query->allows('getGrammar')->returns($grammar);

$builder->allows('getQuery')->returns($query);
```

### Suggested rule addition

Apply the existing expectation-array conversion recursively: when an
entry's value is itself a `Mockery::mock(...)` expectation-array call
(with or without a further-nested `mock()` inside *that* one), convert the
innermost call first, assign it to a generated variable, substitute that
variable as the value in the entry above it, and repeat outward. Bailing
should only happen once no more `mock()` calls remain to unwrap — not on
the first nested one encountered.

## Not included here

The same commit also converted a `shouldReceive()->once()->andReturn(...)`
chain mixing raw Mockery with an inline `mock()` closure, a
`Mockery::spy()` closure-DSL call, and a `makePartial()` +
`shouldReceive()` chain that turned out to need an entirely different
Test Double pattern (an anonymous subclass override, not a double) because
of a runtime-only quirk in how Test Double's passthru mode handles
self-calls. Those needed either translating Mockery call-chain semantics
or diagnosing a runtime behavior difference, not just restructuring
existing syntax, so they're left out of this file — they're not safe to
turn into an automated rule the way the two cases above are.
