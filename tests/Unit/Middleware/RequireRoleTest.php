<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\AuthenticationException;
use App\Exception\AuthorizationException;
use App\Middleware\RequireRole;
use App\Model\User;
use App\Service\AuthServiceInterface;
use PHPUnit\Framework\TestCase;

final class RequireRoleTest extends TestCase
{
    public function testGuestIsRejected(): void
    {
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('user')->willReturn(null);

        $this->expectException(AuthenticationException::class);
        (new RequireRole($auth))->handle(
            new Request('GET', '/admin', [], [], [], []),
            static fn () => Response::html('ok'),
        );
    }

    public function testUserCannotAccessAdminRole(): void
    {
        $user = new User(2, 'Riley', 'user@vending.test', 'hash', 'user');
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('user')->willReturn($user);
        $request = new Request('GET', '/admin', [], [], [], []);
        $request->setAttribute('role', 'admin');

        $this->expectException(AuthorizationException::class);
        (new RequireRole($auth))->handle($request, static fn () => Response::html('ok'));
    }

    public function testAdminCanContinue(): void
    {
        $user = new User(1, 'Avery', 'admin@vending.test', 'hash', 'admin');
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('user')->willReturn($user);
        $request = new Request('GET', '/admin', [], [], [], []);
        $request->setAttribute('role', 'admin');

        $response = (new RequireRole($auth))->handle(
            $request,
            static fn () => Response::html('secret'),
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('secret', $response->content);
    }
}
