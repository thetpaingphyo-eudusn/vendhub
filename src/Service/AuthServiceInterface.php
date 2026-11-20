<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Model\User;

interface AuthServiceInterface
{
    public function user(): ?User;

    public function attempt(string $email, string $password): bool;

    public function verify(string $email, string $password): ?User;

    public function logout(): void;

    /** @throws ValidationException */
    public function register(string $name, string $email, string $password): User;

    public function updateProfile(User $user, string $name, string $email): User;

    public function changePassword(User $user, string $currentPassword, string $newPassword): void;
}
