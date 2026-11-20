<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\AuthenticationException;
use App\Service\JwtService;

final class RequireJwt implements MiddlewareInterface
{
    public function __construct(private JwtService $jwt)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new AuthenticationException('A bearer token is required.');
        }

        $user = $this->jwt->authenticate($token);
        if ($user === null) {
            throw new AuthenticationException('The token is invalid or expired.');
        }

        $request->setUser($user);

        return $next($request);
    }
}
