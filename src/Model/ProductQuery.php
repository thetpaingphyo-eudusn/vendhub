<?php

declare(strict_types=1);

namespace App\Model;

final class ProductQuery
{
    /** @var list<string> */
    public const SORTS = ['name', 'price', 'quantity_available', 'category', 'status'];

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [6, 9, 12, 24];

    public function __construct(
        public int $page,
        public int $perPage,
        public string $sort,
        public string $direction,
        public string $search = '',
        public string $category = '',
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function from(array $input): self
    {
        $requested = (string) ($input['sort'] ?? '');
        $sort = in_array($requested, self::SORTS, true) ? $requested : 'name';
        $direction = strtolower((string) ($input['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = (int) ($input['per_page'] ?? 12);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 12;
        }
        $search = trim((string) ($input['q'] ?? ''));
        $search = preg_replace('/\s+/u', ' ', $search) ?? $search;
        if (mb_strlen($search) > 80) {
            $search = mb_substr($search, 0, 80);
        }
        $category = trim((string) ($input['category'] ?? ''));
        if (mb_strlen($category) > 50) {
            $category = mb_substr($category, 0, 50);
        }

        return new self($page, $perPage, $sort, $direction, $search, $category);
    }
}
