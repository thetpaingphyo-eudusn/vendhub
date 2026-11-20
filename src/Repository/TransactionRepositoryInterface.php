<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Transaction;
use App\Model\TransactionQuery;

interface TransactionRepositoryInterface
{
    public function create(Transaction $transaction): Transaction;

    public function findById(int $id): ?Transaction;

    /** @return list<Transaction> */
    public function latest(int $limit = 50): array;

    /** @return list<Transaction> */
    public function search(TransactionQuery $query): array;

    /** @return list<Transaction> */
    public function forUser(int $userId, int $limit = 50): array;

    public function update(Transaction $transaction): void;

    public function delete(int $id): void;
}
