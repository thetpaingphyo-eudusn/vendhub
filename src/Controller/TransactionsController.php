<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Attribute\Route;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Middleware\RequireRole;
use App\Middleware\RequireSession;
use App\Model\TransactionQuery;
use App\Repository\TransactionRepositoryInterface;
use App\Service\AuthServiceInterface;

final class TransactionsController extends WebController
{
    public function __construct(
        private TransactionRepositoryInterface $transactions,
        AuthServiceInterface $auth,
        View $view,
        Url $url,
    ) {
        parent::__construct($auth, $view, $url);
    }

    #[Route('/admin/transactions', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function index(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $query = TransactionQuery::from($request->query);
        $transactions = $this->transactions->search($query);
        $itemCount = 0;
        $revenue = 0.0;
        foreach ($transactions as $transaction) {
            $itemCount += (int) $transaction->quantity;
            $revenue += (float) $transaction->total;
        }

        return $this->renderAdmin('transactions/index', [
            'title' => 'Transactions',
            'activeNav' => 'transactions',
            'transactions' => $transactions,
            'query' => $query,
            'txCount' => count($transactions),
            'itemCount' => $itemCount,
            'revenueTotal' => number_format($revenue, 3, '.', ''),
        ]);
    }
}
