<?php

namespace Illuminate\Tests\Support;

use JMac\Testing\TestDouble;
use Exception;
use Illuminate\Support\Timebox;
use PHPUnit\Framework\TestCase;

class SupportTimeboxTest extends TestCase
{
    public function testMakeExecutesCallback()
    {
        $callback = function () {
            $this->assertTrue(true);
        };

        (new Timebox)->call($callback, 0);
    }

    public function testMakeWaitsForMicroseconds()
    {
        $mock = TestDouble::for(Timebox::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->expects('usleep');

        $mock->call(function () {
        }, 10000);

        $mock->received('usleep')->times(1);
    }

    public function testMakeShouldNotSleepWhenEarlyReturnHasBeenFlagged()
    {
        $mock = TestDouble::for(Timebox::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->call(function ($timebox) {
            $timebox->returnEarly();
        }, 10000);

        $mock->received('usleep')->never();
    }

    public function testMakeShouldSleepWhenDontEarlyReturnHasBeenFlagged()
    {
        $mock = TestDouble::for(Timebox::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->expects('usleep');

        $mock->call(function ($timebox) {
            $timebox->returnEarly();
            $timebox->dontReturnEarly();
        }, 10000);

        $mock->received('usleep')->times(1);
    }

    public function testMakeWaitsForMicrosecondsWhenExceptionIsThrown()
    {
        $mock = TestDouble::for(Timebox::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->expects('usleep');

        try {
            $this->expectExceptionMessage('Exception within Timebox callback.');

            $mock->call(function () {
                throw new Exception('Exception within Timebox callback.');
            }, 10000);
        } finally {
            $mock->received('usleep')->times(1);
        }
    }

    public function testMakeShouldNotSleepWhenEarlyReturnHasBeenFlaggedAndExceptionIsThrown()
    {
        $mock = TestDouble::for(Timebox::class)->shouldAllowMockingProtectedMethods()->makePartial();

        try {
            $this->expectExceptionMessage('Exception within Timebox callback.');

            $mock->call(function ($timebox) {
                $timebox->returnEarly();
                throw new Exception('Exception within Timebox callback.');
            }, 10000);
        } finally {
            $mock->received('usleep')->never();
        }
    }
}
