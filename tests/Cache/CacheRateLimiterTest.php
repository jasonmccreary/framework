<?php

namespace Illuminate\Tests\Cache;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class CacheRateLimiterTest extends TestCase
{
    public function testTooManyAttemptsReturnTrueIfAlreadyLockedOut()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('get')->with('key', 0)->returns(1);
        $cache->expects('has')->with('key:timer')->returns(true);
        $cache->expects('add')->never();
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $this->assertTrue($rateLimiter->tooManyAttempts('key', 1));
    }

    public function testHitProperlyIncrementsAttemptCount()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('add')->with('key:timer', Argument::type('int'), 1)->returns(true);
        $cache->expects('add')->with('key', 0, 1)->returns(true);
        $cache->expects('increment')->with('key', 1)->returns(1);
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $rateLimiter->hit('key', 1);
    }

    public function testIncrementProperlyIncrementsAttemptCount()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('add')->with('key:timer', Argument::type('int'), 1)->returns(true);
        $cache->expects('add')->with('key', 0, 1)->returns(true);
        $cache->expects('increment')->with('key', 5)->returns(5);
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $rateLimiter->increment('key', 1, 5);
    }

    public function testDecrementProperlyDecrementsAttemptCount()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('add')->with('key:timer', Argument::type('int'), 1)->returns(true);
        $cache->expects('add')->with('key', 0, 1)->returns(true);
        $cache->expects('increment')->with('key', -5)->returns(-5);
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $rateLimiter->decrement('key', 1, 5);
    }

    public function testHitHasNoMemoryLeak()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('add')->with('key:timer', Argument::type('int'), 1)->returns(true);
        $cache->expects('add')->with('key', 0, 1)->returns(false);
        $cache->expects('increment')->with('key', 1)->returns(1);
        $cache->expects('put')->with('key', 1, 1);
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $rateLimiter->hit('key', 1);
    }

    public function testIncrementWithCustomAmountHasNoMemoryLeak()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('add')->with('key:timer', Argument::type('int'), 60)->returns(true);
        $cache->expects('add')->with('key', 0, 60)->returns(false);
        $cache->expects('increment')->with('key', 2)->returns(2);
        $cache->expects('put')->with('key', 2, 60);
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $rateLimiter->increment('key', 60, 2);
    }

    public function testRemainingIsNotNegative(): void
    {
        $cache = TestDouble::for(Cache::class);
        $cache->allows('get')->with('key', 0)->returns(5);
        $cache->allows('getStore')->returns(new ArrayStore);

        $rateLimiter = new RateLimiter($cache);

        $this->assertSame(0, $rateLimiter->remaining('key', 3));
        $this->assertSame(0, $rateLimiter->retriesLeft('key', 3));
    }

    public function testRetriesLeftReturnsCorrectCount()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('get')->with('key', 0)->returns(3);
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $this->assertEquals(2, $rateLimiter->retriesLeft('key', 5));
    }

    public function testClearClearsTheCacheKeys()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('forget')->with('key');
        $cache->expects('forget')->with('key:timer');
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $rateLimiter->clear('key');
    }

    public function testAvailableInReturnsPositiveValues()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->allows('get')->returns(Carbon::now()->subMinute()->getTimestamp(), null);
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $this->assertTrue($rateLimiter->availableIn('key:timer') >= 0);
        $this->assertTrue($rateLimiter->availableIn('key:timer') >= 0);
    }

    public function testAttemptsCallbackReturnsTrue()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('get')->with('key', 0)->returns(0);
        $cache->expects('add')->with('key:timer', Argument::type('int'), 1);
        $cache->expects('add')->with('key', 0, 1)->returns(1);
        $cache->expects('increment')->with('key', 1)->returns(1);
        $cache->allows('getStore')->returns(new ArrayStore);

        $executed = false;

        $rateLimiter = new RateLimiter($cache);

        $rateLimiter->attempt('key', 1, function () use (&$executed) {
            $executed = true;
        }, 1);
        $this->assertTrue($executed);
    }

    public function testAttemptsCallbackReturnsCallbackReturn()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('get')->times(6)->with('key', 0)->returns(0);
        $cache->expects('add')->times(6)->with('key:timer', Argument::type('int'), 1);
        $cache->expects('add')->times(6)->with('key', 0, 1)->returns(1);
        $cache->expects('increment')->times(6)->with('key', 1)->returns(1);
        $cache->allows('getStore')->returns(new ArrayStore);

        $rateLimiter = new RateLimiter($cache);

        $this->assertSame('foo', $rateLimiter->attempt('key', 1, function () {
            return 'foo';
        }, 1));

        $this->assertFalse($rateLimiter->attempt('key', 1, function () {
            return false;
        }, 1));

        $this->assertSame([], $rateLimiter->attempt('key', 1, function () {
            return [];
        }, 1));

        $this->assertSame(0, $rateLimiter->attempt('key', 1, function () {
            return 0;
        }, 1));

        $this->assertSame(0.0, $rateLimiter->attempt('key', 1, function () {
            return 0.0;
        }, 1));

        $this->assertSame('', $rateLimiter->attempt('key', 1, function () {
            return '';
        }, 1));
    }

    public function testAttemptsCallbackReturnsFalse()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('get')->with('key', 0)->returns(2);
        $cache->expects('has')->with('key:timer')->returns(true);
        $cache->allows('getStore')->returns(new ArrayStore);

        $executed = false;

        $rateLimiter = new RateLimiter($cache);

        $this->assertFalse($rateLimiter->attempt('key', 1, function () use (&$executed) {
            $executed = true;
        }, 1));
        $this->assertFalse($executed);
    }

    public function testKeysAreSanitizedFromUnicodeCharacters()
    {
        $cache = TestDouble::for(Cache::class);
        $cache->expects('get')->with('john', 0)->returns(1);
        $cache->expects('has')->with('john:timer')->returns(true);
        $cache->expects('add')->never();
        $cache->allows('getStore')->returns(new ArrayStore);
        $rateLimiter = new RateLimiter($cache);

        $this->assertTrue($rateLimiter->tooManyAttempts('jôhn', 1));
    }

    public function testKeyIsSanitizedOnlyOnce()
    {
        $cache = TestDouble::for(Cache::class);
        $rateLimiter = new RateLimiter($cache);

        $key = "john'doe";
        $cleanedKey = $rateLimiter->cleanRateLimiterKey($key);

        $cache->expects('get')->with($cleanedKey, 0)->returns(1);
        $cache->expects('has')->with("$cleanedKey:timer")->returns(true);
        $cache->expects('add')->never();
        $cache->allows('getStore')->returns(new ArrayStore);

        $this->assertTrue($rateLimiter->tooManyAttempts($key, 1));
    }
}
