<?php

declare(strict_types=1);

namespace App\Validation;

use App\Repository\CategoryRepositoryInterface;

final class ProductValidator
{
    private const MAX_PRICE = '9999999.999';
    private const MAX_QUANTITY = 4294967295;
    public function __construct(private CategoryRepositoryInterface $categories)
    {
    }

    /** @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function validate(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $price = trim((string) ($input['price'] ?? ''));
        $quantity = trim((string) ($input['quantity_available'] ?? ''));
        $reorderPoint = trim((string) ($input['reorder_point'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (contains_html($name)) {
            $errors['name'] = 'Name cannot contain HTML.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'Name must be 150 characters or fewer.';
        }

        if ($price === '') {
            $errors['price'] = 'Price is required.';
        } elseif (preg_match('/^\d+(\.\d{1,3})?$/', $price) !== 1 || !$this->isPositivePriceWithinDatabaseRange($price)) {
            $errors['price'] = 'Price must be greater than zero and no more than ' . self::MAX_PRICE . '.';
        }

        if ($quantity === '') {
            $errors['quantity_available'] = 'Quantity is required.';
        } elseif (preg_match('/^\d+$/', $quantity) !== 1 || !$this->isUnsignedIntWithinRange($quantity)) {
            $errors['quantity_available'] = 'Quantity must be zero or greater and no more than ' . self::MAX_QUANTITY . '.';
        }

        if ($reorderPoint === '') {
            $errors['reorder_point'] = 'Reorder point is required.';
        } elseif (preg_match('/^\d+$/', $reorderPoint) !== 1) {
            $errors['reorder_point'] = 'Reorder point must be zero or greater.';
        } elseif (!$this->isUnsignedIntWithinRange($reorderPoint) || (int) $reorderPoint > 100000) {
            $errors['reorder_point'] = 'Reorder point is too high.';
        }

        $allowed = $this->categories->names();
        if ($category === '') {
            $errors['category'] = 'Category is required.';
        } elseif ($allowed === []) {
            $errors['category'] = 'Create a category first.';
        } elseif (!in_array($category, $allowed, true)) {
            $errors['category'] = 'Choose a valid category.';
        }

        return $errors;
    }

    public function normalizePrice(string $price): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim($price), 2), 2, '');

        return $whole . '.' . str_pad(substr($fraction, 0, 3), 3, '0');
    }

    public function validateQuantity(string $quantity, int $available): ?string
    {
        if ($quantity === '') {
            return 'Quantity is required.';
        }
        if (preg_match('/^\d+$/', $quantity) !== 1) {
            return 'Quantity must be a whole number.';
        }

        if (!$this->isUnsignedIntWithinRange($quantity) || $quantity === '0') {
            return 'Quantity must be at least 1.';
        }
        $value = (int) $quantity;
        if ($value > $available) {
            return 'Not enough stock.';
        }

        return null;
    }

    private function isPositivePriceWithinDatabaseRange(string $price): bool
    {
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $normalized = $whole . '.' . str_pad($fraction, 3, '0');

        return $normalized !== '0.000' && version_compare($normalized, self::MAX_PRICE, '<=');
    }

    private function isUnsignedIntWithinRange(string $value): bool
    {
        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        return strlen($normalized) < 10 || (strlen($normalized) === 10 && $normalized <= (string) self::MAX_QUANTITY);
    }
}
