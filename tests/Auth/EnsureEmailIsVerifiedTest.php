<?php

namespace Illuminate\Tests\Auth;

use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Tests\TestCase;

class EnsureEmailIsVerifiedTest extends TestCase
{
    public function testItCanGenerateDefinitionViaStaticMethod()
    {
        $signature = EnsureEmailIsVerified::redirectTo('route.name');
        $this->assertSame('Illuminate\Auth\Middleware\EnsureEmailIsVerified:route.name', $signature);
    }
}
