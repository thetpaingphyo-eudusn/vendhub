<?php

declare(strict_types=1);

namespace App\Model;

final class Transaction
{
    public function __construct(
        public ?int $id,
        public int $userId,
        public int $productId,
        public int $quantity,
        public string $unitPrice,
        public string $total,
        public ?string $createdAt = null,
        public ?string $userName = null,
        public ?string $productName = null,
        public ?string $productSlug = null,
    ) {
    }
}
