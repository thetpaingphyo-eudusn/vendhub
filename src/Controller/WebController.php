<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Service\AuthServiceInterface;

abstract class WebController
{
    public function __construct(
        protected AuthServiceInterface $auth,
        protected View $view,
        protected Url $url,
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        $data['authUser'] = $this->auth->user();
        $data['csrf'] = (string) ($_SESSION['csrf'] ?? '');
        if (!array_key_exists('flash', $data)) {
            $data['flash'] = $_SESSION['flash'] ?? null;
            unset($_SESSION['flash']);
        }
        if (!array_key_exists('purchaseSuccess', $data)) {
            $data['purchaseSuccess'] = $_SESSION['purchase_success'] ?? null;
            unset($_SESSION['purchase_success']);
        }
        $data['errors'] ??= [];
        $data['old'] ??= [];
        $data['layout'] ??= 'layout';
        $data['area'] ??= 'store';
        $data['activeNav'] ??= '';

        return Response::html($this->view->render($template, $data), $status);
    }

    /** @param array<string, mixed> $data */
    protected function renderAdmin(string $template, array $data = [], int $status = 200): Response
    {
        $data['layout'] = 'admin';
        $data['area'] = 'admin';

        return $this->render($template, $data, $status);
    }

    /** @param array<string, mixed> $data */
    protected function renderAuth(string $template, array $data = [], int $status = 200): Response
    {
        $data['layout'] = 'auth_layout';
        $data['area'] = 'auth';

        return $this->render($template, $data, $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($this->url->path($path));
    }

    protected function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    protected function requireUser(): ?Response
    {
        if ($this->auth->user() === null) {
            return $this->redirect('/login');
        }

        return null;
    }

    protected function requireAdmin(): ?Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return $this->redirect('/admin/login');
        }
        if (!$user->isAdmin()) {
            return $this->render('errors/403', [
                'title' => 'Forbidden',
                'message' => 'You do not have access to this page.',
            ], 403);
        }

        return null;
    }
}
