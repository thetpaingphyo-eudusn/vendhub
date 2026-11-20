<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Core\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConnectionTest extends TestCase
{
    public function testAFailedTransactionRollsBack(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE items (qty INTEGER)');
        $pdo->exec('INSERT INTO items (qty) VALUES (5)');
        $connection = new Connection($pdo);

        try {
            $connection->transaction(static function () use ($connection): void {
                $connection->execute('UPDATE items SET qty = 1');
                throw new RuntimeException('stop');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(5, (int) $connection->selectOne('SELECT qty FROM items')['qty']);
    }
}
