<?php

declare(strict_types=1);

final class AuthService
{
    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        return User::findActiveById((int) $id);
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            flash('error', 'Entre para continuar.');
            redirect('/login');
        }

        return $user;
    }

    public static function guestOnly(): void
    {
        if (self::user() !== null) {
            redirect('/');
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByLogin($email);
        if ($user === null || !(int) $user['active'] || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::login((int) $user['id']);

        return true;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}
