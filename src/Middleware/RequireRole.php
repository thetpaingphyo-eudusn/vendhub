<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\AuthenticationException;
use App\Exception\AuthorizationException;
use App\Service\AuthServiceInterface;

final class RequireRole implements MiddlewareInterface
{
    public function __construct(private AuthServiceInterface $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user() ?? $this->auth->user();
        if ($user === null) {
            throw new AuthenticationException('Authentication is required.');
        }

        $role = (string) $request->attribute('role', 'admin');
        if ($user->role !== $role) {
            throw new AuthorizationException('You do not have access to this page.');
        }

        return $next($request);
    }
}
