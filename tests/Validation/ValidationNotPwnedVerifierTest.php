<?php

namespace Illuminate\Tests\Validation;

use JMac\Testing\TestDouble;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\NotPwnedVerifier;
use PHPUnit\Framework\TestCase;

class ValidationNotPwnedVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function testEmptyValues()
    {
        $httpFactory = TestDouble::for(HttpFactory::class);
        $verifier = new NotPwnedVerifier($httpFactory);

        foreach (['', false, 0] as $password) {
            $this->assertFalse($verifier->verify([
                'value' => $password,
                'threshold' => 0,
            ]));
        }
    }

    public function testApiResponseGoesWrong()
    {
        $httpFactory = TestDouble::for(HttpFactory::class);
        $response = TestDouble::for(Response::class);

        $httpFactory = TestDouble::for(HttpFactory::class);

        $httpFactory->expects('withHeaders')->with(['Add-Padding' => true])->returns($httpFactory);

        $httpFactory->expects('timeout')->with(30)->returns($httpFactory);

        $httpFactory->expects('get')->returns($response);

        $response->expects('successful')->returns(true);

        $response->expects('body')->returns('');

        $verifier = new NotPwnedVerifier($httpFactory);

        $this->assertTrue($verifier->verify([
            'value' => 123123123,
            'threshold' => 0,
        ]));
    }

    public function testApiGoesDown()
    {
        $httpFactory = TestDouble::for(HttpFactory::class);
        $response = TestDouble::for(Response::class);

        $httpFactory->expects('withHeaders')->with(['Add-Padding' => true])->returns($httpFactory);

        $httpFactory->expects('timeout')->with(30)->returns($httpFactory);

        $httpFactory->expects('get')->returns($response);

        $response->expects('successful')->returns(false);

        $verifier = new NotPwnedVerifier($httpFactory);

        $this->assertTrue($verifier->verify([
            'value' => 123123123,
            'threshold' => 0,
        ]));
    }

    public function testMagicHashDoesNotCauseFalsePositive()
    {
        // "aaroZmOk" produces a SHA-1 hash that is all digits prefixed with "0E",
        // which PHP treats as scientific notation (zero) during loose comparison,
        // causing any other all-digit "0E" hash to falsely match.
        $password = 'aaroZmOk';
        $hash = strtoupper(sha1($password));
        $hashPrefix = substr($hash, 0, 5);

        $differentSuffix = '00000000000000000000000000000000000';

        $httpFactory = TestDouble::for(HttpFactory::class);
        $response = TestDouble::for(Response::class);

        $httpFactory->expects('withHeaders')->with(['Add-Padding' => true])->returns($httpFactory);

        $httpFactory->expects('timeout')->with(30)->returns($httpFactory);

        $httpFactory->expects('get')->with('https://api.pwnedpasswords.com/range/'.$hashPrefix)->returns($response);

        $response->expects('successful')->returns(true);

        $response->expects('body')->returns($differentSuffix.':5');

        $verifier = new NotPwnedVerifier($httpFactory);

        $this->assertTrue($verifier->verify([
            'value' => $password,
            'threshold' => 0,
        ]));
    }

    public function testDnsDown()
    {
        $container = Container::getInstance();
        $exception = new ConnectionException();

        $exceptionHandler = TestDouble::for(ExceptionHandler::class);
        $exceptionHandler->expects('report')->with($exception, []);
        $container->bind(ExceptionHandler::class, function () use ($exceptionHandler) {
            return $exceptionHandler;
        });

        $httpFactory = TestDouble::for(HttpFactory::class);

        $httpFactory->expects('withHeaders')->with(['Add-Padding' => true])->returns($httpFactory);

        $httpFactory->expects('timeout')->with(30)->returns($httpFactory);

        $httpFactory->expects('get')->throws($exception);

        $verifier = new NotPwnedVerifier($httpFactory);
        $this->assertTrue($verifier->verify([
            'value' => 123123123,
            'threshold' => 0,
        ]));

        unset($container[ExceptionHandler::class]);
    }
}
