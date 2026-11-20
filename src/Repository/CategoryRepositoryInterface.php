<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Category;

interface CategoryRepositoryInterface
{
    /** @return list<Category> */
    public function all(): array;

    /** @return list<string> */
    public function names(): array;

    public function findById(int $id): ?Category;

    public function findByName(string $name): ?Category;

    public function create(string $name): Category;

    public function update(Category $category, string $previousName): void;

    public function delete(int $id): void;

    public function countProductsUsing(string $name): int;

    /**
     * Deletes the category and all products (plus their transactions) in it.
     *
     * @return list<string> Deleted product slugs (for image cleanup)
     */
    public function deleteWithProducts(int $id, string $name): array;
}
