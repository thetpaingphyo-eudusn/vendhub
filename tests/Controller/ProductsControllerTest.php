<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ProductsController;
use App\Core\Http\Request;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Model\PageResult;
use App\Model\Product;
use App\Model\ProductQuery;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Service\AuthServiceInterface;
use App\Service\PurchaseServiceInterface;
use App\Validation\ProductValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductsControllerTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        new Url('');
        $this->view = new View(dirname(__DIR__, 2) . '/views');
    }

    public function testStoreRejectsABlankName(): void
    {
        $products = $this->products();
        $products->expects($this->never())->method('create');

        $response = $this->controller($products, $this->admin())->store($this->request('POST', [
            'name' => '',
            'price' => '3.99',
            'quantity_available' => '5',
            'reorder_point' => '5',
            'category' => 'Drink',
        ]));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Name is required.', $response->content);
    }

    public function testStoreRejectsANonPositivePrice(): void
    {
        $products = $this->products();
        $products->expects($this->never())->method('create');

        $response = $this->controller($products, $this->admin())->store($this->request('POST', [
            'name' => 'Coke',
            'price' => '0',
            'quantity_available' => '5',
            'reorder_point' => '5',
            'category' => 'Drink',
        ]));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('greater than zero', $response->content);
    }

    public function testStoreRejectsANegativeQuantity(): void
    {
        $products = $this->products();
        $products->expects($this->never())->method('create');

        $response = $this->controller($products, $this->admin())->store($this->request('POST', [
            'name' => 'Coke',
            'price' => '3.99',
            'quantity_available' => '-1',
            'reorder_point' => '5',
            'category' => 'Drink',
        ]));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('zero or greater', $response->content);
    }

    public function testStoreCreatesAValidProduct(): void
    {
        $products = $this->products();
        $products->expects($this->once())->method('create')->with('Coke', '3.990', 5, 'Drink', 5)
            ->willReturn(new Product(1, 'Coke', 'coke', '3.990', 5, 'Drink', 5));

        $response = $this->controller($products, $this->admin())->store($this->request('POST', [
            'name' => 'Coke',
            'price' => '3.99',
            'quantity_available' => '5',
            'reorder_point' => '5',
            'category' => 'Drink',
        ]));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/products', $response->headers['Location']);
    }

    public function testPurchaseRejectsZeroQuantity(): void
    {
        $products = $this->products();
        $products->method('findBySlug')->willReturn($this->coke(12));
        $purchases = $this->purchases();
        $purchases->expects($this->never())->method('purchase');

        $response = $this->controller($products, $this->customer(), $purchases)->purchase(
            $this->request('POST', ['quantity' => '0'], ['slug' => 'coke']),
        );

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('at least 1', $response->content);
    }

    public function testPurchaseRejectsQuantityAboveStock(): void
    {
        $products = $this->products();
        $products->method('findBySlug')->willReturn($this->coke(2));
        $purchases = $this->purchases();
        $purchases->expects($this->never())->method('purchase');

        $response = $this->controller($products, $this->customer(), $purchases)->purchase(
            $this->request('POST', ['quantity' => '5'], ['slug' => 'coke']),
        );

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Not enough stock.', $response->content);
    }

    public function testPurchaseCompletesWhenStockIsAvailable(): void
    {
        $products = $this->products();
        $products->method('findBySlug')->willReturn($this->coke(12));
        $purchases = $this->purchases();
        $purchases->expects($this->once())->method('purchase')->willReturn(new Transaction(9, 2, 1, 1, '3.990', '3.990'));

        $response = $this->controller($products, $this->customer(), $purchases)->purchase(
            $this->request('POST', ['quantity' => '1'], ['slug' => 'coke']),
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/products/coke', $response->headers['Location']);
    }

    public function testShowReturns404ForAnUnknownSlug(): void
    {
        $products = $this->products();
        $products->method('findBySlug')->willReturn(null);

        $response = $this->controller($products, $this->customer())->show(
            $this->request('GET', [], ['slug' => 'missing']),
        );

        $this->assertSame(404, $response->status);
    }

    public function testCustomerCannotOpenManagement(): void
    {
        $products = $this->products();
        $products->expects($this->never())->method('paginate');

        $response = $this->controller($products, $this->customer())->manage($this->request('GET'));

        $this->assertSame(403, $response->status);
    }

    public function testGuestIsSentToLoginBeforePurchase(): void
    {
        $products = $this->products();
        $products->expects($this->never())->method('findBySlug');

        $response = $this->controller($products, null)->purchase(
            $this->request('POST', ['quantity' => '1'], ['slug' => 'coke']),
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testIndexIgnoresAnUnsafeSortColumn(): void
    {
        $products = $this->products();
        $products->expects($this->once())->method('paginate')->with($this->callback(
            static fn (ProductQuery $query): bool => $query->sort === 'name' && $query->direction === 'asc',
        ))->willReturn(new PageResult([], 1, 8, 0));
        $products->method('countInStock')->willReturn(0);

        $response = $this->controller($products, null)->index(new Request(
            'GET',
            '/products',
            ['sort' => 'price;drop', 'dir' => 'asc'],
            [],
            [],
            [],
        ));

        $this->assertSame(200, $response->status);
    }

    public function testDestroyRequiresAdmin(): void
    {
        $products = $this->products();
        $products->expects($this->never())->method('deleteBySlug');

        $response = $this->controller($products, $this->customer())->destroy(
            $this->request('POST', [], ['slug' => 'coke']),
        );

        $this->assertSame(403, $response->status);
    }

    public function testUpdateRejectsInvalidPayloadWithoutSaving(): void
    {
        $products = $this->products();
        $products->method('findBySlug')->willReturn($this->coke(5));
        $products->expects($this->never())->method('update');

        $response = $this->controller($products, $this->admin())->update(
            $this->request('POST', [
                'name' => '',
                'price' => '-1',
                'quantity_available' => '2',
                'reorder_point' => '1',
                'category' => 'Drink',
            ], ['slug' => 'coke']),
        );

        $this->assertSame(422, $response->status);
    }

    public function testRestockRejectsNonPositiveAmount(): void
    {
        $products = $this->products();
        $products->method('findBySlug')->willReturn($this->coke(5));
        $products->expects($this->never())->method('adjustQuantity');

        $response = $this->controller($products, $this->admin())->restock(
            $this->request('POST', ['amount' => '0'], ['slug' => 'coke']),
        );

        $this->assertSame(302, $response->status);
    }

    private function controller(
        ProductRepositoryInterface $products,
        ?User $user,
        ?PurchaseServiceInterface $purchases = null,
    ): ProductsController {
        $categories = $this->createMock(CategoryRepositoryInterface::class);
        $categories->method('all')->willReturn([]);
        $categories->method('names')->willReturn(['Drink', 'Snack']);

        return new ProductsController(
            $products,
            $categories,
            $purchases ?? $this->purchases(),
            new ProductValidator($categories),
            $this->auth($user),
            $this->view,
            new Url(''),
        );
    }

    /** @return ProductRepositoryInterface&MockObject */
    private function products(): ProductRepositoryInterface
    {
        return $this->createMock(ProductRepositoryInterface::class);
    }

    /** @return PurchaseServiceInterface&MockObject */
    private function purchases(): PurchaseServiceInterface
    {
        return $this->createMock(PurchaseServiceInterface::class);
    }

    private function auth(?User $user): AuthServiceInterface
    {
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('user')->willReturn($user);

        return $auth;
    }

    /** @param array<string, mixed> $body
     * @param array<string, string> $params
     */
    private function request(string $method, array $body = [], array $params = []): Request
    {
        $request = new Request($method, '/products', [], $body, [], []);
        $request->setRouteParams($params);

        return $request;
    }

    private function admin(): User
    {
        return new User(1, 'Avery Admin', 'admin@vending.test', 'hash', 'admin');
    }

    private function customer(): User
    {
        return new User(2, 'Riley Customer', 'user@vending.test', 'hash', 'user');
    }

    private function coke(int $stock): Product
    {
        return new Product(1, 'Coke', 'coke', '3.990', $stock);
    }
}
