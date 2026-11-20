<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\Api\ProductsApiController;
use App\Core\Http\Request;
use App\Model\PageResult;
use App\Model\Product;
use App\Model\ProductQuery;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Service\PurchaseServiceInterface;
use App\Validation\ProductValidator;
use PHPUnit\Framework\TestCase;

/**
 * Integration: API list + purchase happy/failure path through controller + validator.
 */
final class ProductApiFlowIntegrationTest extends TestCase
{
    public function testListThenPurchaseHappyPath(): void
    {
        $product = new Product(1, 'Pepsi', 'pepsi', '6.885', 8, 'Drink', 3);
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('paginate')->willReturn(new PageResult([$product], 1, 12, 1));
        $products->method('findBySlug')->with('pepsi')->willReturn($product);

        $purchases = $this->createMock(PurchaseServiceInterface::class);
        $purchases->expects($this->once())->method('purchase')->willReturn(
            new Transaction(5, 2, 1, 1, '6.885', '6.885'),
        );

        $controller = $this->controller($products, $purchases);

        $list = $controller->index(new Request('GET', '/api/products', [], [], [], []));
        $this->assertSame(200, $list->status);
        $this->assertStringContainsString('Pepsi', $list->content);

        $buy = new Request('POST', '/api/products/pepsi/purchase', [], ['quantity' => '1'], [], []);
        $buy->setRouteParams(['slug' => 'pepsi']);
        $buy->setUser(new User(2, 'Riley', 'user@vending.test', 'hash', 'user'));
        $response = $controller->purchase($buy);

        $this->assertSame(201, $response->status);
        $this->assertStringContainsString('"total":"6.885"', $response->content);
    }

    public function testPurchaseFailsValidationWithoutCallingService(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlug')->willReturn(new Product(1, 'Water', 'water', '0.500', 2));
        $purchases = $this->createMock(PurchaseServiceInterface::class);
        $purchases->expects($this->never())->method('purchase');

        $buy = new Request('POST', '/api/products/water/purchase', [], ['quantity' => '0'], [], []);
        $buy->setRouteParams(['slug' => 'water']);
        $buy->setUser(new User(2, 'Riley', 'user@vending.test', 'hash', 'user'));

        $response = $this->controller($products, $purchases)->purchase($buy);
        $this->assertSame(422, $response->status);
    }

    private function controller(
        ProductRepositoryInterface $products,
        PurchaseServiceInterface $purchases,
    ): ProductsApiController {
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('names')->willReturn(['Drink', 'Snack']);

        return new ProductsApiController($products, $purchases, new ProductValidator($categories));
    }
}
