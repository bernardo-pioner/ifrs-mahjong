<?php

declare(strict_types=1);

final class UserController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAdmin();
        $this->view('users/index', [
            'title' => 'Usuários',
            'user' => $user,
            'currentPath' => '/usuarios',
            'users' => User::all(),
        ]);
    }
}
