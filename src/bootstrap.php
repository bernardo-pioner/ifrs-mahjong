<?php

declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';

$config = require ROOT_PATH . '/config.php';

function config(?string $key = null): mixed
{
    global $config;

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? null;
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require ROOT_PATH . '/src/db.php';

spl_autoload_register(static function (string $class): void {
    $paths = [
        ROOT_PATH . '/src/' . $class . '.php',
        ROOT_PATH . '/src/models/' . $class . '.php',
        ROOT_PATH . '/src/controllers/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});

db();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(400);
        flash('error', 'Sessão expirada. Tente novamente.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

function view(string $name, array $data = []): void
{
    $data['flash'] = $data['flash'] ?? takeFlash();
    $data['appName'] = $data['appName'] ?? (string) config('app_name');
    extract($data, EXTR_SKIP);

    ob_start();
    require ROOT_PATH . '/views/' . $name . '.php';
    $content = ob_get_clean();

    require ROOT_PATH . '/views/layout.php';
}

function request(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function isActivePath(string $currentPath, string $path): bool
{
    return $currentPath === $path;
}
