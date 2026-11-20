<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\ProductQuery;
use PHPUnit\Framework\TestCase;

final class ProductQueryTest extends TestCase
{
    public function testUnsafeSortFallsBackToName(): void
    {
        $query = ProductQuery::from(['sort' => 'price; DELETE FROM products', 'dir' => 'sideways', 'page' => '0']);

        $this->assertSame('name', $query->sort);
        $this->assertSame('asc', $query->direction);
        $this->assertSame(1, $query->page);
        $this->assertSame(12, $query->perPage);
        $this->assertSame('', $query->search);
    }

    public function testSearchAndPerPageAreNormalized(): void
    {
        $query = ProductQuery::from([
            'q' => '  coke  ',
            'per_page' => '9',
            'sort' => 'price',
            'dir' => 'desc',
        ]);

        $this->assertSame('coke', $query->search);
        $this->assertSame(9, $query->perPage);
        $this->assertSame('price', $query->sort);
        $this->assertSame('desc', $query->direction);

        $invalid = ProductQuery::from(['per_page' => '99', 'q' => str_repeat('x', 100)]);
        $this->assertSame(12, $invalid->perPage);
        $this->assertSame(80, mb_strlen($invalid->search));
    }

    public function testCategoryAndStatusSortsAreAllowed(): void
    {
        $category = ProductQuery::from(['sort' => 'category', 'dir' => 'desc']);
        $this->assertSame('category', $category->sort);
        $this->assertSame('desc', $category->direction);

        $status = ProductQuery::from(['sort' => 'status']);
        $this->assertSame('status', $status->sort);
        $this->assertSame('asc', $status->direction);
    }

    public function testCategoryFilterIsNormalized(): void
    {
        $query = ProductQuery::from(['category' => '  Snack  ']);
        $this->assertSame('Snack', $query->category);

        $empty = ProductQuery::from([]);
        $this->assertSame('', $empty->category);

        $long = ProductQuery::from(['category' => str_repeat('c', 80)]);
        $this->assertSame(50, mb_strlen($long->category));
    }
}
