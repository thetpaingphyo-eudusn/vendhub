<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\AuthorizationException;

final class VerifyCsrf implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true) || $request->wantsJson()) {
            return $next($request);
        }

        $sent = (string) $request->input('_csrf', '');
        $known = (string) ($_SESSION['csrf'] ?? '');
        if ($known === '' || !hash_equals($known, $sent)) {
            throw new AuthorizationException('The form expired. Refresh the page and try again.');
        }

        return $next($request);
    }
}
