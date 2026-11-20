<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Model\User;
use App\Repository\UserRepositoryInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(
        private Config $config,
        private UserRepositoryInterface $users,
    ) {
    }

    public function issue(User $user): string
    {
        $now = time();
        $ttl = max(300, (int) $this->config->get('jwt.ttl', 3600));

        return JWT::encode([
            'iss' => (string) $this->config->get('app.url'),
            'sub' => $user->id,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ], (string) $this->config->get('jwt.secret'), 'HS256');
    }

    public function authenticate(string $token): ?User
    {
        try {
            $decoded = JWT::decode($token, new Key((string) $this->config->get('jwt.secret'), 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        return $this->users->findById((int) $decoded->sub);
    }
}
