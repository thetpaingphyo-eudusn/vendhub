<?php

declare(strict_types=1);

namespace App\Model;

final class Product
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $slug,
        public string $price,
        public int $quantityAvailable,
        public string $category = 'Drink',
        public int $reorderPoint = 5,
    ) {
    }

    /** @return 'out'|'low'|'ok' */
    public function stockStatus(): string
    {
        if ($this->quantityAvailable <= 0) {
            return 'out';
        }

        if ($this->quantityAvailable <= max(0, $this->reorderPoint)) {
            return 'low';
        }

        return 'ok';
    }

    /** @return array{id: int|null, name: string, slug: string, price: string, quantity_available: int, category: string, reorder_point: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'quantity_available' => $this->quantityAvailable,
            'category' => $this->category,
            'reorder_point' => $this->reorderPoint,
        ];
    }
}
