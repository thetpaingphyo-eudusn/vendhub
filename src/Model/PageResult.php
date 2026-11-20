<?php

declare(strict_types=1);

namespace App\Model;

final class PageResult
{
    /** @param list<Product> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
