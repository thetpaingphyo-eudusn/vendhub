<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Http\Request;
use App\Security\ClientIp;
use App\Security\Https;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase
{
    public function testItIgnoresForwardedIpWithoutTrustedProxy(): void
    {
        $request = new Request(
            'GET',
            '/',
            [],
            [],
            ['x-forwarded-for' => '203.0.113.9'],
            ['REMOTE_ADDR' => '10.0.0.5'],
        );

        $this->assertSame('10.0.0.5', ClientIp::resolve($request, []));
    }

    public function testItUsesForwardedIpWhenProxyIsTrusted(): void
    {
        $request = new Request(
            'GET',
            '/',
            [],
            [],
            ['x-forwarded-for' => '203.0.113.9, 10.0.0.5'],
            ['REMOTE_ADDR' => '10.0.0.5'],
        );

        $this->assertSame('203.0.113.9', ClientIp::resolve($request, ['10.0.0.5']));
    }

    public function testHttpsDetectsForwardedProtoOnlyFromTrustedProxy(): void
    {
        $request = new Request(
            'GET',
            '/',
            [],
            [],
            ['x-forwarded-proto' => 'https'],
            ['REMOTE_ADDR' => '10.0.0.5'],
        );

        $this->assertFalse(Https::isSecure($request, []));
        $this->assertTrue(Https::isSecure($request, ['10.0.0.5']));
    }
}
