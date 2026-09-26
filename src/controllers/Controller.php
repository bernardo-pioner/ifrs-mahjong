<?php

declare(strict_types=1);

abstract class Controller
{
    protected function requireAuth(): array
    {
        return AuthService::requireUser();
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireAuth();
        if (($user['role'] ?? '') !== 'admin') {
            flash('error', 'Acesso restrito a administradores.');
            redirect('/');
        }

        return $user;
    }

    protected function guestOnly(): void
    {
        AuthService::guestOnly();
    }

    /** @param array<string, mixed> $data */
    protected function view(string $name, array $data = []): void
    {
        view($name, $data);
    }
}
