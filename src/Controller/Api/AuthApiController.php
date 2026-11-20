<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Attribute\Route;
use App\Core\Config;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Middleware\RateLimit;
use App\Security\RateLimiter;
use App\Service\AuthServiceInterface;
use App\Service\JwtService;

final class AuthApiController
{
    public function __construct(
        private AuthServiceInterface $auth,
        private JwtService $jwt,
        private RateLimiter $rateLimiter,
        private Config $config,
    ) {
    }

    #[Route('/api/auth/token', methods: 'POST', middleware: [RateLimit::class])]
    public function token(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $user = $this->auth->verify($email, $password);
        if ($user === null) {
            return Response::json(['message' => 'The email or password is incorrect.'], 401);
        }

        $normalized = strtolower($email);
        if ($normalized !== '') {
            $this->rateLimiter->clear('auth:email:' . $normalized);
        }

        return Response::json([
            'token' => $this->jwt->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => max(300, (int) $this->config->get('jwt.ttl', 3600)),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
