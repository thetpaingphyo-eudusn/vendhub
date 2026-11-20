<?php

declare(strict_types=1);

namespace App\Validation;

final class PasswordStrength
{
    public static function message(): string
    {
        return 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.';
    }

    public static function isStrong(string $password): bool
    {
        return self::error($password) === null;
    }

    public static function error(string $password): ?string
    {
        if ($password === '') {
            return 'Password is required.';
        }

        if (
            strlen($password) < 8
            || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/\d/', $password) !== 1
            || preg_match('/[^A-Za-z0-9]/', $password) !== 1
        ) {
            return self::message();
        }

        return null;
    }
}
