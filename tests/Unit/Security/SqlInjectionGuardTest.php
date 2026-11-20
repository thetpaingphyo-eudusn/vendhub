<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Model\ProductQuery;
use App\Model\TransactionQuery;
use PHPUnit\Framework\TestCase;

final class SqlInjectionGuardTest extends TestCase
{
    /** @dataProvider maliciousSorts */
    public function testProductSortInjectionFallsBackToSafeColumn(string $payload): void
    {
        $query = ProductQuery::from(['sort' => $payload, 'dir' => "desc; DROP TABLE users--"]);
        $this->assertContains($query->sort, ProductQuery::SORTS);
        $this->assertSame('asc', $query->direction);
    }

    /** @dataProvider maliciousSorts */
    public function testTransactionSortInjectionFallsBackToSafeColumn(string $payload): void
    {
        $query = TransactionQuery::from(['sort' => $payload, 'dir' => 'asc']);
        $this->assertContains($query->sort, TransactionQuery::SORTS);
    }

    public function testSearchAndCategoryAreTrimmedNotExecuted(): void
    {
        $query = ProductQuery::from([
            'q' => "  '; DROP TABLE products;--  ",
            'category' => "Drink' OR '1'='1",
        ]);

        $this->assertSame("'; DROP TABLE products;--", $query->search);
        $this->assertSame("Drink' OR '1'='1", $query->category);
    }

    /** @return list<array{0: string}> */
    public static function maliciousSorts(): array
    {
        return [
            ["name; DROP TABLE products;--"],
            ['price) ASC, (SELECT 1)--'],
            ["quantity_available; DELETE FROM users--"],
            ['unknown_column'],
        ];
    }
}
