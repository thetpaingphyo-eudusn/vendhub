<?php

declare(strict_types=1);

namespace App\Core\Http;

final class Url
{
    private static string $base = '';

    public function __construct(string $basePath = '')
    {
        self::$base = rtrim($basePath, '/');
    }

    public function path(string $path): string
    {
        return self::to($path);
    }

    public static function to(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return self::$base . '/' . ltrim($path, '/');
    }
}
