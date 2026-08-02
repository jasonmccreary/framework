<?php

namespace Illuminate\Tests\Routing;

use JMac\Testing\TestDouble;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;

class RoutingRedirectorTest extends TestCase
{
    protected $headers;
    protected $request;
    protected $url;
    protected $session;
    protected $redirect;

    protected function setUp(): void
    {
        $this->headers = TestDouble::for(HeaderBag::class);

        $this->request = TestDouble::for(Request::class);
        $this->request->shouldReceive('isMethod')->andReturn(true)->byDefault();
        $this->request->shouldReceive('method')->andReturn('GET')->byDefault();
        $this->request->shouldReceive('route')->andReturn(true)->byDefault();
        $this->request->shouldReceive('ajax')->andReturn(false)->byDefault();
        $this->request->shouldReceive('expectsJson')->andReturn(false)->byDefault();
        $this->request->headers = $this->headers;

        $this->url = TestDouble::for(UrlGenerator::class);
        $this->url->allows('getRequest')->returns($this->request);
        $this->url->allows('to')->with('bar', [], null)->returns('http://foo.com/bar');
        $this->url->allows('to')->with('bar', [], true)->returns('https://foo.com/bar');
        $this->url->allows('to')->with('login', [], null)->returns('http://foo.com/login');
        $this->url->allows('to')->with('http://foo.com/bar', [], null)->returns('http://foo.com/bar');
        $this->url->allows('to')->with('/', [], null)->returns('http://foo.com/');
        $this->url->allows('to')->with('http://foo.com/bar?signature=secret', [], null)->returns('http://foo.com/bar?signature=secret');

        $this->session = TestDouble::for(Store::class);

        $this->redirect = new Redirector($this->url);
        $this->redirect->setSession($this->session);
    }

    public function testBasicRedirectTo()
    {
        $response = $this->redirect->to('bar');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://foo.com/bar', $response->getTargetUrl());
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals($this->session, $response->getSession());
    }

    public function testComplexRedirectTo()
    {
        $response = $this->redirect->to('bar', 303, ['X-RateLimit-Limit' => 60, 'X-RateLimit-Remaining' => 59], true);

        $this->assertSame('https://foo.com/bar', $response->getTargetUrl());
        $this->assertEquals(303, $response->getStatusCode());
        $this->assertEquals(60, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(59, $response->headers->get('X-RateLimit-Remaining'));
    }

    public function testGuestPutCurrentUrlInSession()
    {
        $this->url->allows('full')->returns('http://foo.com/bar');
        $this->session->expects('put')->with('url.intended', 'http://foo.com/bar');

        $response = $this->redirect->guest('login');

        $this->assertSame('http://foo.com/login', $response->getTargetUrl());
    }

    public function testGuestPutPreviousUrlInSession()
    {
        $this->request->expects('isMethod')->with('GET')->returns(false);
        $this->session->expects('put')->with('url.intended', 'http://foo.com/bar');
        $this->url->expects('previous')->returns('http://foo.com/bar');

        $response = $this->redirect->guest('login');

        $this->assertSame('http://foo.com/login', $response->getTargetUrl());
    }

    public function testIntendedRedirectToIntendedUrlInSession()
    {
        $this->session->allows('pull')->with('url.intended', '/')->returns('http://foo.com/bar');

        $response = $this->redirect->intended();

        $this->assertSame('http://foo.com/bar', $response->getTargetUrl());
    }

    public function testIntendedWithoutIntendedUrlInSession()
    {
        $this->session->expects('forget')->with('url.intended');

        // without fallback url
        $this->session->allows('pull')->with('url.intended', '/')->returns('/');
        $response = $this->redirect->intended();
        $this->assertSame('http://foo.com/', $response->getTargetUrl());

        // with a fallback url
        $this->session->allows('pull')->with('url.intended', 'bar')->returns('bar');
        $response = $this->redirect->intended('bar');
        $this->assertSame('http://foo.com/bar', $response->getTargetUrl());
    }

    public function testRefreshRedirectToCurrentUrl()
    {
        $this->request->allows('path')->returns('http://foo.com/bar');
        $response = $this->redirect->refresh();
        $this->assertSame('http://foo.com/bar', $response->getTargetUrl());
    }

    public function testBackRedirectToHttpReferer()
    {
        $this->headers->allows('has')->with('referer')->returns(true);
        $this->url->allows('previous')->returns('http://foo.com/bar');
        $response = $this->redirect->back();
        $this->assertSame('http://foo.com/bar', $response->getTargetUrl());
    }

    public function testAwayDoesntValidateTheUrl()
    {
        $response = $this->redirect->away('bar');
        $this->assertSame('bar', $response->getTargetUrl());
    }

    public function testSecureRedirectToHttpsUrl()
    {
        $response = $this->redirect->secure('bar');
        $this->assertSame('https://foo.com/bar', $response->getTargetUrl());
    }

    public function testAction()
    {
        $this->url->allows('action')->with('bar@index', [])->returns('http://foo.com/bar');
        $response = $this->redirect->action('bar@index');
        $this->assertSame('http://foo.com/bar', $response->getTargetUrl());
    }

    public function testRoute()
    {
        $this->url->allows('route')->with('home')->returns('http://foo.com/bar');
        $this->url->allows('route')->with('home', [])->returns('http://foo.com/bar');

        $response = $this->redirect->route('home');
        $this->assertSame('http://foo.com/bar', $response->getTargetUrl());
    }

    public function testSignedRoute()
    {
        $this->url->allows('signedRoute')->with('home', [], null)->returns('http://foo.com/bar?signature=secret');

        $response = $this->redirect->signedRoute('home');
        $this->assertSame('http://foo.com/bar?signature=secret', $response->getTargetUrl());
    }

    public function testTemporarySignedRoute()
    {
        $this->url->allows('temporarySignedRoute')->with('home', 10, [])->returns('http://foo.com/bar?signature=secret');

        $response = $this->redirect->temporarySignedRoute('home', 10);
        $this->assertSame('http://foo.com/bar?signature=secret', $response->getTargetUrl());
    }

    public function testItSetsAndGetsValidIntendedUrl()
    {
        $this->session->expects('put')->with('url.intended', 'http://foo.com/bar');
        $this->session->allows('get')->returns('http://foo.com/bar');

        $result = $this->redirect->setIntendedUrl('http://foo.com/bar');
        $this->assertInstanceOf(Redirector::class, $result);

        $this->assertSame('http://foo.com/bar', $this->redirect->getIntendedUrl());
    }
}
