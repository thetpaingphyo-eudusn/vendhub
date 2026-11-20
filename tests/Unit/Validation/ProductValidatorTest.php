<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Repository\CategoryRepositoryInterface;
use App\Validation\ProductValidator;
use PHPUnit\Framework\TestCase;

final class ProductValidatorTest extends TestCase
{
    public function testValidDrinkProductPasses(): void
    {
        $errors = $this->validator()->validate([
            'name' => 'Coke',
            'price' => '3.99',
            'quantity_available' => '10',
            'reorder_point' => '5',
            'category' => 'Drink',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('3.990', $this->validator()->normalizePrice('3.99'));
    }

    public function testRequiredFieldsAndPositivePrice(): void
    {
        $errors = $this->validator()->validate([
            'name' => '',
            'price' => '0',
            'quantity_available' => '',
            'reorder_point' => '',
            'category' => '',
        ]);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('price', $errors);
        $this->assertArrayHasKey('quantity_available', $errors);
        $this->assertArrayHasKey('reorder_point', $errors);
        $this->assertArrayHasKey('category', $errors);
    }

    public function testQuantityMustBeNonNegativeWholeNumber(): void
    {
        $errors = $this->validator()->validate([
            'name' => 'Water',
            'price' => '0.5',
            'quantity_available' => '-3',
            'reorder_point' => '1',
            'category' => 'Drink',
        ]);

        $this->assertArrayHasKey('quantity_available', $errors);
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $errors = $this->validator()->validate([
            'name' => 'Pepsi',
            'price' => '6.885',
            'quantity_available' => '4',
            'reorder_point' => '2',
            'category' => 'Unknown',
        ]);

        $this->assertArrayHasKey('category', $errors);
    }

    public function testPurchaseQuantityRules(): void
    {
        $validator = $this->validator();
        $this->assertNotNull($validator->validateQuantity('', 5));
        $this->assertNotNull($validator->validateQuantity('0', 5));
        $this->assertNotNull($validator->validateQuantity('6', 5));
        $this->assertNull($validator->validateQuantity('2', 5));
    }

    public function testDatabaseNumericLimitsAreEnforced(): void
    {
        $validator = $this->validator();
        $errors = $validator->validate([
            'name' => 'Water',
            'price' => '10000000.000',
            'quantity_available' => '4294967296',
            'reorder_point' => '4294967296',
            'category' => 'Drink',
        ]);

        $this->assertArrayHasKey('price', $errors);
        $this->assertArrayHasKey('quantity_available', $errors);
        $this->assertArrayHasKey('reorder_point', $errors);
        $this->assertNotNull($validator->validateQuantity('4294967296', 4294967295));
    }

    private function validator(): ProductValidator
    {
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('names')->willReturn(['Drink', 'Snack']);

        return new ProductValidator($categories);
    }
}
