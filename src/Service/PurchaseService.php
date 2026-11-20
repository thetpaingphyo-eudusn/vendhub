<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database\Connection;
use App\Exception\InsufficientStockException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\ProductRepositoryInterface;
use App\Repository\TransactionRepositoryInterface;

final class PurchaseService implements PurchaseServiceInterface
{
    public function __construct(
        private Connection $connection,
        private ProductRepositoryInterface $products,
        private TransactionRepositoryInterface $transactions,
    ) {
    }

    public function purchase(User $user, string $slug, int $quantity): Transaction
    {
        if ($quantity < 1) {
            throw new ValidationException(['quantity' => 'Quantity must be at least 1.']);
        }

        if ($user->id === null) {
            throw new NotFoundException('User not found.');
        }

        return $this->connection->transaction(function () use ($user, $slug, $quantity): Transaction {
            $product = $this->products->findBySlugForUpdate($slug);
            if ($product === null || $product->id === null) {
                throw new NotFoundException('Product not found.');
            }
            if ($product->quantityAvailable < $quantity) {
                throw new InsufficientStockException('Not enough stock.');
            }

            $this->products->adjustQuantity($product->id, -$quantity);

            return $this->transactions->create(new Transaction(
                null,
                $user->id,
                $product->id,
                $quantity,
                $product->price,
                $this->multiply($product->price, $quantity),
            ));
        });
    }

    private function multiply(string $price, int $quantity): string
    {
        if (function_exists('bcmul')) {
            return bcmul($price, (string) $quantity, 3);
        }

        return number_format(((float) $price) * $quantity, 3, '.', '');
    }
}
