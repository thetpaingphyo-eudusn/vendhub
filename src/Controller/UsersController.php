<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Attribute\Route;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Middleware\RequireRole;
use App\Middleware\RequireSession;
use App\Middleware\VerifyCsrf;
use App\Repository\UserRepositoryInterface;
use App\Service\AuthServiceInterface;
use PDOException;

final class UsersController extends WebController
{
    public function __construct(
        private UserRepositoryInterface $users,
        AuthServiceInterface $auth,
        View $view,
        Url $url,
    ) {
        parent::__construct($auth, $view, $url);
    }

    #[Route('/admin/users', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function index(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $users = $this->users->all();
        $adminCount = 0;
        $userCount = 0;
        foreach ($users as $user) {
            if ($user->isAdmin()) {
                $adminCount++;
            } else {
                $userCount++;
            }
        }

        return $this->renderAdmin('users/index', [
            'title' => 'Users',
            'activeNav' => 'users',
            'users' => $users,
            'adminCount' => $adminCount,
            'userCount' => $userCount,
        ]);
    }

    #[Route('/admin/users/{id}/role', methods: 'POST', middleware: [RequireSession::class, RequireRole::class, VerifyCsrf::class], role: 'admin')]
    public function update(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $user = $this->users->findById((int) $request->route('id'));
        $actor = $this->auth->user();
        $role = (string) $request->input('role', '');
        if ($user === null || $actor === null || !in_array($role, ['admin', 'user'], true)) {
            $this->flash('That user could not be updated.', 'error');

            return $this->redirect('/admin/users');
        }

        if ($user->id === $actor->id) {
            $this->flash('You cannot change your own role.', 'error');

            return $this->redirect('/admin/users');
        }

        if ($user->isAdmin() && $role !== 'admin' && $this->users->countByRole('admin') <= 1) {
            $this->flash('The last admin cannot be demoted.', 'error');

            return $this->redirect('/admin/users');
        }

        $user->role = $role;
        $this->users->update($user);
        $this->flash('User updated.');

        return $this->redirect('/admin/users');
    }

    #[Route('/admin/users/{id}/delete', methods: 'POST', middleware: [RequireSession::class, RequireRole::class, VerifyCsrf::class], role: 'admin')]
    public function destroy(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $user = $this->users->findById((int) $request->route('id'));
        $actor = $this->auth->user();
        if ($user === null || $actor === null) {
            $this->flash('That user could not be deleted.', 'error');

            return $this->redirect('/admin/users');
        }

        if ($user->id === $actor->id) {
            $this->flash('You cannot delete your own account.', 'error');

            return $this->redirect('/admin/users');
        }

        if ($user->isAdmin() && $this->users->countByRole('admin') <= 1) {
            $this->flash('The last admin cannot be deleted.', 'error');

            return $this->redirect('/admin/users');
        }

        try {
            $this->users->delete((int) $user->id);
            $this->flash('User deleted.');
        } catch (PDOException $exception) {
            $message = (string) $exception->getCode() === '23000'
                ? 'This user has purchases and cannot be deleted.'
                : 'The user could not be deleted.';
            $this->flash($message, 'error');
        }

        return $this->redirect('/admin/users');
    }
}
