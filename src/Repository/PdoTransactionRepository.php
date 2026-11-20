<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database\Connection;
use App\Model\Transaction;
use App\Model\TransactionQuery;

final class PdoTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(Transaction $transaction): Transaction
    {
        $this->connection->execute(
            'INSERT INTO transactions (user_id, product_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)',
            [
                $transaction->userId,
                $transaction->productId,
                $transaction->quantity,
                $transaction->unitPrice,
                $transaction->total,
            ],
        );

        return new Transaction(
            $this->connection->lastInsertId(),
            $transaction->userId,
            $transaction->productId,
            $transaction->quantity,
            $transaction->unitPrice,
            $transaction->total,
        );
    }

    public function findById(int $id): ?Transaction
    {
        $row = $this->connection->selectOne(
            'SELECT t.id, t.user_id, t.product_id, t.quantity, t.unit_price, t.total, t.created_at,
                    u.name AS user_name, p.name AS product_name, p.slug AS product_slug
             FROM transactions t
             INNER JOIN users u ON u.id = t.user_id
             INNER JOIN products p ON p.id = t.product_id
             WHERE t.id = ?',
            [$id],
        );

        return $row === null ? null : $this->map($row);
    }

    public function latest(int $limit = 50): array
    {
        return $this->search(TransactionQuery::from([
            'sort' => 'when',
            'dir' => 'desc',
            'limit' => $limit,
        ]));
    }

    public function search(TransactionQuery $query): array
    {
        $orderBy = match ($query->sort) {
            'customer' => 'u.name',
            'product' => 'p.name',
            'qty' => 't.quantity',
            'unit_price' => 't.unit_price',
            'total' => 't.total',
            default => 't.created_at',
        };
        $direction = $query->direction === 'asc' ? 'ASC' : 'DESC';
        $where = [];
        $params = [];

        if ($query->start !== '') {
            $where[] = 't.created_at >= ?';
            $params[] = strlen($query->start) === 10 ? $query->start . ' 00:00:00' : $query->start;
        }
        if ($query->end !== '') {
            $where[] = 't.created_at <= ?';
            $params[] = strlen($query->end) === 10 ? $query->end . ' 23:59:59' : $query->end;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $limit = max(1, min(200, $query->limit));

        $rows = $this->connection->select(
            "SELECT t.id, t.user_id, t.product_id, t.quantity, t.unit_price, t.total, t.created_at,
                    u.name AS user_name, p.name AS product_name, p.slug AS product_slug
             FROM transactions t
             INNER JOIN users u ON u.id = t.user_id
             INNER JOIN products p ON p.id = t.product_id
             {$whereSql}
             ORDER BY {$orderBy} {$direction}, t.id DESC
             LIMIT {$limit}",
            $params,
        );

        return array_map($this->map(...), $rows);
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = $this->connection->select(
            "SELECT t.id, t.user_id, t.product_id, t.quantity, t.unit_price, t.total, t.created_at,
                    u.name AS user_name, p.name AS product_name, p.slug AS product_slug
             FROM transactions t
             INNER JOIN users u ON u.id = t.user_id
             INNER JOIN products p ON p.id = t.product_id
             WHERE t.user_id = ?
             ORDER BY t.id DESC
             LIMIT {$limit}",
            [$userId],
        );

        return array_map($this->map(...), $rows);
    }

    public function update(Transaction $transaction): void
    {
        $this->connection->execute(
            'UPDATE transactions SET quantity = ?, unit_price = ?, total = ? WHERE id = ?',
            [$transaction->quantity, $transaction->unitPrice, $transaction->total, $transaction->id],
        );
    }

    public function delete(int $id): void
    {
        $this->connection->execute('DELETE FROM transactions WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Transaction
    {
        return new Transaction(
            (int) $row['id'],
            (int) $row['user_id'],
            (int) $row['product_id'],
            (int) $row['quantity'],
            (string) $row['unit_price'],
            (string) $row['total'],
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['user_name']) ? (string) $row['user_name'] : null,
            isset($row['product_name']) ? (string) $row['product_name'] : null,
            isset($row['product_slug']) ? (string) $row['product_slug'] : null,
        );
    }
}
