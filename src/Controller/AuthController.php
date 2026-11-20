<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Attribute\Route;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Exception\ValidationException;
use App\Middleware\RateLimit;
use App\Middleware\RequireSession;
use App\Middleware\VerifyCsrf;
use App\Security\RateLimiter;
use App\Service\AuthServiceInterface;
use App\Validation\RegisterValidator;

final class AuthController extends WebController
{
    public function __construct(
        AuthServiceInterface $auth,
        private RegisterValidator $validator,
        private RateLimiter $rateLimiter,
        View $view,
        Url $url,
    ) {
        parent::__construct($auth, $view, $url);
    }

    #[Route('/', methods: 'GET')]
    public function home(Request $request): Response
    {
        return $this->redirect('/products');
    }

    #[Route('/login', methods: 'GET')]
    public function loginForm(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user !== null) {
            return $this->redirect($user->isAdmin() ? '/admin' : '/products');
        }

        return $this->renderAuth('auth/login', ['title' => 'Sign In']);
    }

    #[Route('/login', methods: 'POST', middleware: [RateLimit::class, VerifyCsrf::class])]
    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            return $this->renderAuth('auth/login', [
                'title' => 'Sign In',
                'errors' => ['email' => 'Email and password are required.'],
                'old' => ['email' => $email],
            ], 422);
        }

        if (!$this->auth->attempt($email, $password)) {
            return $this->renderAuth('auth/login', [
                'title' => 'Sign In',
                'errors' => ['email' => 'The email or password is incorrect.'],
                'old' => ['email' => $email],
            ], 422);
        }

        $user = $this->auth->user();
        if ($user !== null && $user->isAdmin()) {
            $this->auth->logout();

            return $this->renderAuth('auth/login', [
                'title' => 'Sign In',
                'errors' => ['email' => 'Admin accounts must sign in at Admin Login.'],
                'old' => ['email' => $email],
            ], 422);
        }

        $this->clearAuthRateLimit($email);

        return $this->redirect('/products');
    }

    #[Route('/admin/login', methods: 'GET')]
    public function adminLoginForm(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user !== null) {
            return $this->redirect($user->isAdmin() ? '/admin' : '/products');
        }

        return $this->renderAuth('auth/admin_login', ['title' => 'Admin Sign In']);
    }

    #[Route('/admin/login', methods: 'POST', middleware: [RateLimit::class, VerifyCsrf::class])]
    public function adminLogin(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            return $this->renderAuth('auth/admin_login', [
                'title' => 'Admin Sign In',
                'errors' => ['email' => 'Email and password are required.'],
                'old' => ['email' => $email],
            ], 422);
        }

        if (!$this->auth->attempt($email, $password)) {
            return $this->renderAuth('auth/admin_login', [
                'title' => 'Admin Sign In',
                'errors' => ['email' => 'The email or password is incorrect.'],
                'old' => ['email' => $email],
            ], 422);
        }

        $user = $this->auth->user();
        if ($user === null || !$user->isAdmin()) {
            $this->auth->logout();

            return $this->renderAuth('auth/admin_login', [
                'title' => 'Admin Sign In',
                'errors' => ['email' => 'This account does not have admin access.'],
                'old' => ['email' => $email],
            ], 422);
        }

        $this->clearAuthRateLimit($email);

        return $this->redirect('/admin');
    }

    #[Route('/logout', methods: 'POST', middleware: [RequireSession::class, VerifyCsrf::class])]
    public function logout(Request $request): Response
    {
        $wasAdmin = $this->auth->user()?->isAdmin() ?? false;
        $this->auth->logout();

        $next = (string) $request->input('next', '');
        if ($next === '/admin/login' || ($wasAdmin && $next === '')) {
            return $this->redirect('/admin/login');
        }

        return $this->redirect('/login');
    }

    #[Route('/register', methods: 'GET')]
    public function registerForm(Request $request): Response
    {
        if ($this->auth->user() !== null) {
            return $this->redirect('/products');
        }

        return $this->renderAuth('auth/register', ['title' => 'Register']);
    }

    #[Route('/register', methods: 'POST', middleware: [RateLimit::class, VerifyCsrf::class])]
    public function register(Request $request): Response
    {
        $input = [
            'name' => trim((string) $request->input('name', '')),
            'email' => trim((string) $request->input('email', '')),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ];
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return $this->renderAuth('auth/register', [
                'title' => 'Register',
                'errors' => $errors,
                'old' => $input,
            ], 422);
        }

        try {
            $this->auth->register($input['name'], $input['email'], $input['password']);
        } catch (ValidationException $exception) {
            return $this->renderAuth('auth/register', [
                'title' => 'Register',
                'errors' => $exception->errors,
                'old' => $input,
            ], 422);
        }

        $this->clearAuthRateLimit($input['email']);
        $this->flash('Account created.');

        return $this->redirect('/products');
    }

    private function clearAuthRateLimit(string $email): void
    {
        $normalized = strtolower(trim($email));
        if ($normalized !== '') {
            $this->rateLimiter->clear('auth:email:' . $normalized);
        }
    }
}
