<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Transaction;
use App\Model\User;

interface PurchaseServiceInterface
{
    public function purchase(User $user, string $slug, int $quantity): Transaction;
}
