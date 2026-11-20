<?php

declare(strict_types=1);

namespace App\Core\Support;

final class Slug
{
    /** @var list<string> */
    private const RESERVED = ['admin', 'manage', 'create', 'purchase', 'login', 'register'];

    public static function from(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '' || in_array($slug, self::RESERVED, true)) {
            return 'product';
        }

        return $slug;
    }
}
