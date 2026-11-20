<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Url;
use App\Exception\AuthenticationException;
use App\Service\AuthServiceInterface;

final class RequireSession implements MiddlewareInterface
{
    public function __construct(private AuthServiceInterface $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->user() === null) {
            if ($request->wantsJson()) {
                throw new AuthenticationException('Authentication is required.');
            }

            $login = str_starts_with($request->path, '/admin') ? '/admin/login' : '/login';

            return Response::redirect(Url::to($login));
        }

        return $next($request);
    }
}
