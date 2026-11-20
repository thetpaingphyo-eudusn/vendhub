<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\WebController;
use App\Core\Attribute\Route;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Middleware\RequireRole;
use App\Middleware\RequireSession;
use App\Model\ProductQuery;
use App\Repository\ProductRepositoryInterface;
use App\Repository\TransactionRepositoryInterface;
use App\Service\AuthServiceInterface;

final class NotificationsController extends WebController
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private TransactionRepositoryInterface $transactions,
        AuthServiceInterface $auth,
        View $view,
        Url $url,
    ) {
        parent::__construct($auth, $view, $url);
    }

    #[Route('/admin/notifications', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function index(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $items = [];
        $latestPurchaseId = 0;

        foreach ($this->transactions->latest(8) as $tx) {
            $id = (int) ($tx->id ?? 0);
            $latestPurchaseId = max($latestPurchaseId, $id);
            $qty = (int) $tx->quantity;
            $product = (string) ($tx->productName ?? 'Product');
            $customer = (string) ($tx->userName ?? 'Customer');
            $items[] = [
                'id' => 'purchase-' . $id,
                'type' => 'purchase',
                'title' => 'New purchase',
                'body' => $customer . ' bought ' . $qty . ' × ' . $product,
                'href' => $this->url->path('/admin/transactions'),
                'created_at' => (string) ($tx->createdAt ?? ''),
                'meta' => ['purchase_id' => $id],
            ];
        }

        $page = 1;
        do {
            $result = $this->products->paginate(ProductQuery::from([
                'page' => $page,
                'per_page' => 24,
                'sort' => 'quantity_available',
                'dir' => 'asc',
            ]));

            foreach ($result->items as $product) {
                $status = $product->stockStatus();
                if ($status === 'ok') {
                    continue;
                }

                $qty = (int) $product->quantityAvailable;
                if ($status === 'out') {
                    $items[] = [
                        'id' => 'out-' . $product->slug,
                        'type' => 'out',
                        'title' => 'Out of stock',
                        'body' => $product->name . ' has 0 left.',
                        'href' => $this->url->path('/admin/products/' . $product->slug . '/edit'),
                        'created_at' => '',
                        'meta' => ['slug' => $product->slug, 'qty' => 0],
                    ];
                } else {
                    $items[] = [
                        'id' => 'low-' . $product->slug,
                        'type' => 'low',
                        'title' => 'Low stock',
                        'body' => $product->name . ' has only ' . $qty . ' left (reorder at ' . $product->reorderPoint . ').',
                        'href' => $this->url->path('/admin/products/' . $product->slug . '/edit'),
                        'created_at' => '',
                        'meta' => ['slug' => $product->slug, 'qty' => $qty],
                    ];
                }
            }

            $page++;
        } while ($page <= $result->lastPage() && $page <= 10);

        return Response::json([
            'generated_at' => date('c'),
            'latest_purchase_id' => $latestPurchaseId,
            'count' => count($items),
            'items' => array_slice($items, 0, 20),
        ]);
    }
}
