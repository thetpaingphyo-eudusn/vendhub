<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Http\Request;

final class ClientIp
{
    /**
     * @param list<string> $trustedProxies
     */
    public static function resolve(Request $request, array $trustedProxies = []): string
    {
        $remote = trim((string) ($request->server['REMOTE_ADDR'] ?? '0.0.0.0'));
        if ($remote === '') {
            $remote = '0.0.0.0';
        }

        if ($trustedProxies === [] || !self::isTrustedProxy($remote, $trustedProxies)) {
            return $remote;
        }

        $forwarded = trim((string) ($request->headers['x-forwarded-for'] ?? ''));
        if ($forwarded === '') {
            return $remote;
        }

        $parts = array_map('trim', explode(',', $forwarded));
        $candidate = $parts[0] ?? '';
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }

        return $remote;
    }

    /**
     * @param list<string> $trustedProxies
     */
    public static function isTrustedProxy(string $ip, array $trustedProxies): bool
    {
        foreach ($trustedProxies as $proxy) {
            $proxy = trim($proxy);
            if ($proxy === '') {
                continue;
            }
            if (str_contains($proxy, '/')) {
                if (self::inCidr($ip, $proxy)) {
                    return true;
                }
                continue;
            }
            if (hash_equals($proxy, $ip)) {
                return true;
            }
        }

        return false;
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($subnet === null || $mask === null || !is_numeric($mask)) {
            return false;
        }

        $ipBytes = inet_pton($ip);
        $subnetBytes = inet_pton($subnet);
        if ($ipBytes === false || $subnetBytes === false || strlen($ipBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $mask = (int) $mask;
        $bits = strlen($ipBytes) * 8;
        if ($mask < 0 || $mask > $bits) {
            return false;
        }

        $bytes = intdiv($mask, 8);
        $remainder = $mask % 8;
        if ($bytes > 0 && substr($ipBytes, 0, $bytes) !== substr($subnetBytes, 0, $bytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }

        $maskByte = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBytes[$bytes]) & $maskByte) === (ord($subnetBytes[$bytes]) & $maskByte);
    }
}
