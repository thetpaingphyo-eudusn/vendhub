<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Exception\InsufficientStockException;
use App\Model\Product;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Repository\TransactionRepositoryInterface;
use App\Service\PurchaseService;
use App\Validation\ProductValidator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration: validator + purchase service + PDO transaction boundary.
 */
final class PurchaseFlowIntegrationTest extends TestCase
{
    public function testValidQuantityPurchasesAndWritesLedger(): void
    {
        $product = new Product(1, 'Coke', 'coke', '3.990', 10, 'Drink', 5);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlugForUpdate')->with('coke')->willReturn($product);
        $products->expects($this->once())->method('adjustQuantity')->with(1, -2);

        $transactions = $this->createMock(TransactionRepositoryInterface::class);
        $transactions->expects($this->once())->method('create')->willReturnCallback(
            static fn (Transaction $tx): Transaction => new Transaction(
                11,
                $tx->userId,
                $tx->productId,
                $tx->quantity,
                $tx->unitPrice,
                $tx->total,
            ),
        );

        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('names')->willReturn(['Drink']);
        $validator = new ProductValidator($categories);
        $this->assertNull($validator->validateQuantity('2', 10));

        $sale = (new PurchaseService($this->connection(), $products, $transactions))->purchase(
            new User(2, 'Riley', 'user@vending.test', 'hash', 'user'),
            'coke',
            2,
        );

        $this->assertSame(11, $sale->id);
        $this->assertSame('7.980', $sale->total);
    }

    public function testOverStockIsBlockedBeforeInventoryChanges(): void
    {
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('names')->willReturn(['Drink']);
        $validator = new ProductValidator($categories);
        $this->assertSame('Not enough stock.', $validator->validateQuantity('5', 2));

        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlugForUpdate')->willReturn(new Product(1, 'Coke', 'coke', '3.990', 2));
        $products->expects($this->never())->method('adjustQuantity');
        $transactions = $this->createMock(TransactionRepositoryInterface::class);
        $transactions->expects($this->never())->method('create');

        $this->expectException(InsufficientStockException::class);
        (new PurchaseService($this->connection(), $products, $transactions))->purchase(
            new User(2, 'Riley', 'user@vending.test', 'hash', 'user'),
            'coke',
            5,
        );
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new Connection($pdo);
    }
}
