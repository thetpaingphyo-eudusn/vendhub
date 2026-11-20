<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database\Connection;
use App\Core\Support\Slug;
use App\Model\PageResult;
use App\Model\Product;
use App\Model\ProductQuery;

final class PdoProductRepository implements ProductRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function paginate(ProductQuery $query): PageResult
    {
        $direction = $query->direction === 'desc' ? 'DESC' : 'ASC';
        $orderBy = match ($query->sort) {
            'price' => "price {$direction}",
            'quantity_available' => "quantity_available {$direction}",
            'category' => "category {$direction}, name ASC",
            'status' => "CASE WHEN quantity_available <= 0 THEN 0 WHEN quantity_available <= reorder_point THEN 1 ELSE 2 END {$direction}, name ASC",
            default => "name {$direction}",
        };
        [$where, $params] = $this->filterClause($query);

        $totalRow = $this->connection->selectOne("SELECT COUNT(*) AS total FROM products {$where}", $params);
        $total = (int) ($totalRow['total'] ?? 0);
        $offset = ($query->page - 1) * $query->perPage;

        $rows = $this->connection->select(
            "SELECT id, name, slug, price, quantity_available, category, reorder_point
             FROM products
             {$where}
             ORDER BY {$orderBy}
             LIMIT {$query->perPage} OFFSET {$offset}",
            $params,
        );

        return new PageResult(array_map($this->map(...), $rows), $query->page, $query->perPage, $total);
    }

    public function countInStock(ProductQuery $query): int
    {
        [$where, $params] = $this->filterClause($query, requireInStock: true);

        $row = $this->connection->selectOne(
            "SELECT COUNT(*) AS total FROM products {$where}",
            $params,
        );

        return (int) ($row['total'] ?? 0);
    }

    public function findBySlug(string $slug): ?Product
    {
        $row = $this->connection->selectOne(
            'SELECT id, name, slug, price, quantity_available, category, reorder_point FROM products WHERE slug = ?',
            [$slug],
        );

        return $row === null ? null : $this->map($row);
    }

    public function findBySlugForUpdate(string $slug): ?Product
    {
        $row = $this->connection->selectOne(
            'SELECT id, name, slug, price, quantity_available, category, reorder_point FROM products WHERE slug = ? FOR UPDATE',
            [$slug],
        );

        return $row === null ? null : $this->map($row);
    }

    public function findByCategory(string $category): array
    {
        $rows = $this->connection->select(
            'SELECT id, name, slug, price, quantity_available, category, reorder_point
             FROM products
             WHERE category = ?
             ORDER BY name ASC',
            [$category],
        );

        return array_map($this->map(...), $rows);
    }

    public function create(
        string $name,
        string $price,
        int $quantity,
        string $category = 'Drink',
        int $reorderPoint = 5,
    ): Product {
        $slug = $this->uniqueSlug(Slug::from($name));
        $category = $this->normalizeCategory($category);
        $reorderPoint = $this->normalizeReorderPoint($reorderPoint);
        $this->connection->execute(
            'INSERT INTO products (name, slug, price, quantity_available, category, reorder_point) VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $slug, $price, $quantity, $category, $reorderPoint],
        );

        return new Product(
            $this->connection->lastInsertId(),
            $name,
            $slug,
            $price,
            $quantity,
            $category,
            $reorderPoint,
        );
    }

    public function update(Product $product): void
    {
        $product->category = $this->normalizeCategory($product->category);
        $product->reorderPoint = $this->normalizeReorderPoint($product->reorderPoint);
        $this->connection->execute(
            'UPDATE products SET name = ?, price = ?, quantity_available = ?, category = ?, reorder_point = ? WHERE id = ?',
            [
                $product->name,
                $product->price,
                $product->quantityAvailable,
                $product->category,
                $product->reorderPoint,
                $product->id,
            ],
        );
    }

    public function deleteBySlug(string $slug): void
    {
        $this->connection->execute('DELETE FROM products WHERE slug = ?', [$slug]);
    }

    public function adjustQuantity(int $id, int $delta): void
    {
        $this->connection->execute(
            'UPDATE products SET quantity_available = quantity_available + ? WHERE id = ?',
            [$delta, $id],
        );
    }

    private function normalizeCategory(string $category): string
    {
        $category = trim($category);
        if ($category === '' || mb_strlen($category) > 50) {
            return 'Drink';
        }

        return $category;
    }

    private function normalizeReorderPoint(int $reorderPoint): int
    {
        return max(0, min(100000, $reorderPoint));
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function filterClause(ProductQuery $query, bool $requireInStock = false): array
    {
        $parts = [];
        $params = [];

        if ($requireInStock) {
            $parts[] = 'quantity_available > 0';
        }

        if ($query->search !== '') {
            $parts[] = '(name LIKE ? OR slug LIKE ? OR category LIKE ?)';
            $like = '%' . $query->search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($query->category !== '') {
            $parts[] = 'category = ?';
            $params[] = $query->category;
        }

        $where = $parts === [] ? '' : 'WHERE ' . implode(' AND ', $parts);

        return [$where, $params];
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while ($this->findBySlug($slug) !== null) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Product
    {
        return new Product(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['price'],
            (int) $row['quantity_available'],
            $this->normalizeCategory((string) ($row['category'] ?? 'Drink')),
            $this->normalizeReorderPoint((int) ($row['reorder_point'] ?? 5)),
        );
    }
}
