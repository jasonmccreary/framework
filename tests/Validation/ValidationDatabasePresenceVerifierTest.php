<?php

namespace Illuminate\Tests\Validation;

use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use Closure;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Validation\DatabasePresenceVerifier;
use PHPUnit\Framework\TestCase;
use stdClass;

class ValidationDatabasePresenceVerifierTest extends TestCase
{
    public function testBasicCount()
    {
        $verifier = new DatabasePresenceVerifier($db = TestDouble::for(ConnectionResolverInterface::class));
        $verifier->setConnection('connection');
        $db->expects('connection')->with('connection')->returns($conn = TestDouble::for(stdClass::class));
        $conn->expects('table')->with('table')->returns($builder = TestDouble::for(stdClass::class));
        $builder->expects('useWritePdo')->returns($builder);
        $builder->allows('where')->with('column', '=', 'value')->returns($builder);
        $extra = ['foo' => 'NULL', 'bar' => 'NOT_NULL', 'baz' => 'taylor', 'faz' => true, 'not' => '!admin'];
        $builder->allows('whereNull')->with('foo');
        $builder->allows('whereNotNull')->with('bar');
        $builder->allows('where')->with('baz', 'taylor');
        $builder->allows('where')->with('faz', true);
        $builder->allows('where')->with('not', '!=', 'admin');
        $builder->expects('count')->returns(100);

        $this->assertEquals(100, $verifier->getCount('table', 'column', 'value', null, null, $extra));
    }

    public function testBasicCountWithClosures()
    {
        $verifier = new DatabasePresenceVerifier($db = TestDouble::for(ConnectionResolverInterface::class));
        $verifier->setConnection('connection');
        $db->expects('connection')->with('connection')->returns($conn = TestDouble::for(stdClass::class));
        $conn->expects('table')->with('table')->returns($builder = TestDouble::for(stdClass::class));
        $builder->expects('useWritePdo')->returns($builder);
        $builder->allows('where')->with('column', '=', 'value')->returns($builder);
        $closure = function ($query) {
            $query->where('closure', 1);
        };
        $extra = ['foo' => 'NULL', 'bar' => 'NOT_NULL', 'baz' => 'taylor', 'faz' => true, 'not' => '!admin', 0 => $closure];
        $builder->allows('whereNull')->with('foo');
        $builder->allows('whereNotNull')->with('bar');
        $builder->allows('where')->with('baz', 'taylor');
        $builder->allows('where')->with('faz', true);
        $builder->allows('where')->with('not', '!=', 'admin');
        $builder->allows('where')->with(Argument::type(Closure::class))->resolves(function () use ($builder, $closure) {
            $closure($builder);
        });
        $builder->allows('where')->with('closure', 1);
        $builder->expects('count')->returns(100);

        $this->assertEquals(100, $verifier->getCount('table', 'column', 'value', null, null, $extra));
    }

    public function testGetCountWithValidExcludeId()
    {
        $verifier = new DatabasePresenceVerifier($db = TestDouble::for(ConnectionResolverInterface::class));
        $verifier->setConnection('connection');
        $db->expects('connection')->with('connection')->returns($conn = TestDouble::for(stdClass::class));
        $conn->expects('table')->with('table')->returns($builder = TestDouble::for(stdClass::class));
        $builder->expects('useWritePdo')->returns($builder);
        $builder->allows('where')->with('column', '=', 'value')->returns($builder);
        $builder->allows('where')->with('id', '<>', 123)->returns($builder);
        $builder->expects('count')->returns(100);

        $this->assertEquals(100, $verifier->getCount('table', 'column', 'value', 123, 'id', []));
    }
}
