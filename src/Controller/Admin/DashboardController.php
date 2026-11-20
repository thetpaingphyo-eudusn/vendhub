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
use App\Model\Transaction;
use App\Repository\ProductRepositoryInterface;
use App\Repository\TransactionRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\AuthServiceInterface;

final class DashboardController extends WebController
{
    public function __construct(
        AuthServiceInterface $auth,
        View $view,
        Url $url,
        private ProductRepositoryInterface $products,
        private UserRepositoryInterface $users,
        private TransactionRepositoryInterface $transactions,
    ) {
        parent::__construct($auth, $view, $url);
    }

    #[Route('/admin', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function index(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $allTx = $this->transactions->latest(200);
        $productTotal = $this->products->paginate(ProductQuery::from(['per_page' => 12, 'page' => 1]))->total;

        return $this->renderAdmin('admin/dashboard', [
            'title' => 'Dashboard',
            'activeNav' => 'dashboard',
            'productTotal' => $productTotal,
            'userTotal' => count($this->users->all()),
            'outOfStockTotal' => $this->outOfStockTotal(),
            'salesTotal' => count($allTx),
            'recentTransactions' => array_slice($allTx, 0, 6),
            'salesLabels' => $this->salesLabels(),
            'salesCounts' => $this->salesCounts($allTx),
            'topProducts' => $this->topProducts($allTx),
        ]);
    }

    private function outOfStockTotal(): int
    {
        $out = 0;
        $page = 1;

        do {
            $result = $this->products->paginate(ProductQuery::from([
                'page' => $page,
                'per_page' => 24,
            ]));

            foreach ($result->items as $product) {
                if ((int) $product->quantityAvailable <= 0) {
                    $out++;
                }
            }

            $page++;
        } while ($page <= $result->lastPage() && $page <= 20);

        return $out;
    }

    /** @return list<string> */
    private function salesLabels(): array
    {
        $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = date('M j', strtotime('-' . $i . ' days'));
        }

        return $labels;
    }

    /**
     * @param list<Transaction> $transactions
     * @return list<int>
     */
    private function salesCounts(array $transactions): array
    {
        $buckets = [];
        for ($i = 6; $i >= 0; $i--) {
            $buckets[date('Y-m-d', strtotime('-' . $i . ' days'))] = 0;
        }

        foreach ($transactions as $tx) {
            $day = $tx->createdAt !== null ? substr($tx->createdAt, 0, 10) : '';
            if ($day !== '' && array_key_exists($day, $buckets)) {
                $buckets[$day]++;
            }
        }

        return array_values($buckets);
    }

    /**
     * @param list<Transaction> $transactions
     * @return list<array{label: string, value: int, color: string}>
     */
    private function topProducts(array $transactions): array
    {
        $counts = [];
        foreach ($transactions as $tx) {
            $name = trim((string) ($tx->productName ?? 'Unknown'));
            if ($name === '') {
                $name = 'Unknown';
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        if ($counts === []) {
            return [['label' => 'No sales', 'value' => 1, 'color' => '#e5e7eb']];
        }

        arsort($counts);
        $colors = ['#22c55e', '#3b82f6', '#f59e0b', '#a78bfa', '#94a3b8'];
        $top = [];
        $index = 0;
        $other = 0;

        foreach ($counts as $label => $value) {
            if ($index < 3) {
                $top[] = [
                    'label' => $label,
                    'value' => $value,
                    'color' => $colors[$index],
                ];
                $index++;
                continue;
            }
            $other += $value;
        }

        if ($other > 0) {
            $top[] = [
                'label' => 'Others',
                'value' => $other,
                'color' => $colors[3],
            ];
        }

        return $top;
    }
}
