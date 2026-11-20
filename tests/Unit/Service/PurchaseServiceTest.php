<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Database\Connection;
use App\Exception\InsufficientStockException;
use App\Model\Product;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\ProductRepositoryInterface;
use App\Repository\TransactionRepositoryInterface;
use App\Service\PurchaseService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PurchaseServiceTest extends TestCase
{
    public function testPurchaseAboveStockDoesNotChangeInventory(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlugForUpdate')->willReturn(new Product(1, 'Coke', 'coke', '3.990', 2));
        $products->expects($this->never())->method('adjustQuantity');
        $transactions = $this->createMock(TransactionRepositoryInterface::class);
        $transactions->expects($this->never())->method('create');

        $service = new PurchaseService($this->connection(), $products, $transactions);

        $this->expectException(InsufficientStockException::class);
        $service->purchase(new User(2, 'Riley', 'user@vending.test', 'hash', 'user'), 'coke', 5);
    }

    public function testPurchaseWithinStockAdjustsQuantityAndLogsTheSale(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlugForUpdate')->willReturn(new Product(1, 'Pepsi', 'pepsi', '6.885', 4));
        $products->expects($this->once())->method('adjustQuantity')->with(1, -2);
        $transactions = $this->createMock(TransactionRepositoryInterface::class);
        $transactions->expects($this->once())->method('create')->with($this->callback(
            static fn (Transaction $transaction): bool => $transaction->total === '13.770' && $transaction->quantity === 2,
        ))->willReturnCallback(static fn (Transaction $transaction): Transaction => new Transaction(
            8,
            $transaction->userId,
            $transaction->productId,
            $transaction->quantity,
            $transaction->unitPrice,
            $transaction->total,
        ));

        $result = (new PurchaseService($this->connection(), $products, $transactions))->purchase(
            new User(2, 'Riley', 'user@vending.test', 'hash', 'user'),
            'pepsi',
            2,
        );

        $this->assertSame('13.770', $result->total);
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new Connection($pdo);
    }
}
