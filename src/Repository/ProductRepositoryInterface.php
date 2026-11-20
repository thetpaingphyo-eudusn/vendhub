<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\PageResult;
use App\Model\Product;
use App\Model\ProductQuery;

interface ProductRepositoryInterface
{
    public function paginate(ProductQuery $query): PageResult;

    public function countInStock(ProductQuery $query): int;

    public function findBySlug(string $slug): ?Product;

    public function findBySlugForUpdate(string $slug): ?Product;

    /** @return list<Product> */
    public function findByCategory(string $category): array;

    public function create(
        string $name,
        string $price,
        int $quantity,
        string $category = 'Drink',
        int $reorderPoint = 5,
    ): Product;

    public function update(Product $product): void;

    public function deleteBySlug(string $slug): void;

    public function adjustQuantity(int $id, int $delta): void;
}
