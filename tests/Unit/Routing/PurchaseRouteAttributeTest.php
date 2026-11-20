<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use App\Core\Attribute\Route;
use App\Controller\ProductsController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PurchaseRouteAttributeTest extends TestCase
{
    public function testPurchaseActionDeclaresAnSeoFriendlyRoute(): void
    {
        $attributes = (new ReflectionMethod(ProductsController::class, 'purchase'))->getAttributes(Route::class);

        $this->assertCount(1, $attributes);
        $route = $attributes[0]->newInstance();
        $this->assertSame('/products/{slug}/purchase', $route->path);
        $this->assertContains('POST', $route->methods());
    }
}
