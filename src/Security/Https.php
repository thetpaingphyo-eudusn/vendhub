<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Http\Request;

final class Https
{
    /**
     * @param list<string> $trustedProxies
     */
    public static function isSecure(Request $request, array $trustedProxies = []): bool
    {
        $https = (string) ($request->server['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        if ((string) ($request->server['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        $remote = trim((string) ($request->server['REMOTE_ADDR'] ?? ''));
        if ($remote !== '' && ClientIp::isTrustedProxy($remote, $trustedProxies)) {
            $proto = strtolower((string) ($request->headers['x-forwarded-proto'] ?? ''));
            if ($proto === 'https') {
                return true;
            }
        }

        return false;
    }
}
