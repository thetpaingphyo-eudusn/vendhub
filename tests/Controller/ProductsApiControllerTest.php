<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Api\ProductsApiController;
use App\Core\Http\Request;
use App\Model\Product;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Service\PurchaseServiceInterface;
use App\Validation\ProductValidator;
use PHPUnit\Framework\TestCase;

final class ProductsApiControllerTest extends TestCase
{
    public function testPurchaseWithoutAUserReturns401(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects($this->never())->method('findBySlug');
        $controller = $this->controller($products, $this->createMock(PurchaseServiceInterface::class));

        $response = $controller->purchase($this->request(['quantity' => '1']));

        $this->assertSame(401, $response->status);
    }

    public function testPurchaseWithAValidUserReturns201(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlug')->willReturn(new Product(1, 'Coke', 'coke', '3.990', 12));
        $purchases = $this->createMock(PurchaseServiceInterface::class);
        $purchases->expects($this->once())->method('purchase')->willReturn(
            new Transaction(4, 2, 1, 1, '3.990', '3.990'),
        );
        $controller = $this->controller($products, $purchases);
        $request = $this->request(['quantity' => '1']);
        $request->setUser(new User(2, 'Riley', 'user@vending.test', 'hash', 'user'));

        $response = $controller->purchase($request);

        $this->assertSame(201, $response->status);
        $this->assertStringContainsString('"total":"3.990"', $response->content);
    }

    public function testStoreRejectsABadPriceWithoutSaving(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects($this->never())->method('create');
        $controller = $this->controller($products, $this->createMock(PurchaseServiceInterface::class));
        $request = $this->request([
            'name' => 'Coke',
            'price' => '-2',
            'quantity_available' => '4',
            'reorder_point' => '5',
            'category' => 'Drink',
        ]);
        $request->setUser(new User(1, 'Avery', 'admin@vending.test', 'hash', 'admin'));

        $response = $controller->store($request);

        $this->assertSame(422, $response->status);
    }

    public function testNonAdminCannotCreateProducts(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects($this->never())->method('create');
        $controller = $this->controller($products, $this->createMock(PurchaseServiceInterface::class));
        $request = $this->request([
            'name' => 'Coke',
            'price' => '3.99',
            'quantity_available' => '4',
            'reorder_point' => '5',
            'category' => 'Drink',
        ], '/api/products');
        $request->setUser(new User(2, 'Riley', 'user@vending.test', 'hash', 'user'));

        $response = $controller->store($request);

        $this->assertSame(403, $response->status);
    }

    public function testShowReturns404ForUnknownSlug(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlug')->willReturn(null);
        $controller = $this->controller($products, $this->createMock(PurchaseServiceInterface::class));
        $request = new Request('GET', '/api/products/missing', [], [], [], []);
        $request->setRouteParams(['slug' => 'missing']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status);
    }

    public function testPurchaseRejectsOverStock(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('findBySlug')->willReturn(new Product(1, 'Coke', 'coke', '3.990', 1));
        $purchases = $this->createMock(PurchaseServiceInterface::class);
        $purchases->expects($this->never())->method('purchase');
        $controller = $this->controller($products, $purchases);
        $request = $this->request(['quantity' => '5']);
        $request->setUser(new User(2, 'Riley', 'user@vending.test', 'hash', 'user'));

        $response = $controller->purchase($request);

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

    /** @param array<string, mixed> $body */
    private function request(array $body, string $path = '/api/products/coke/purchase'): Request
    {
        $request = new Request('POST', $path, [], $body, [], []);
        if (str_contains($path, '{') || str_contains($path, 'coke')) {
            $request->setRouteParams(['slug' => 'coke']);
        }

        return $request;
    }
}
