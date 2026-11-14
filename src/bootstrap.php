<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

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

function formatarPontuacao(int|string|null $valor): string
{
    if ($valor === null || $valor === '') {
        return '—';
    }

    return number_format((int) $valor, 0, ',', '.');
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

function view(string $viewName, array $data = []): void
{
    $data['flash'] = $data['flash'] ?? takeFlash();
    $data['appName'] = $data['appName'] ?? (string) config('app_name');
    extract($data, EXTR_SKIP);

    ob_start();
    require ROOT_PATH . '/views/' . $viewName . '.php';
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

function isAdmin(?array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function clubLogoUrl(): string
{
    return '/assets/images/' . rawurlencode('logo mini.png');
}

function userFirstName(?array $user): string
{
    $name = userDisplayName($user);
    if ($name === 'Participante') {
        return $name;
    }

    return explode(' ', $name)[0];
}

function userDisplayName(?array $user): string
{
    $nickname = trim((string) ($user['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $username = trim((string) ($user['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    $name = trim((string) ($user['name'] ?? ''));

    return $name !== '' ? $name : 'Participante';
}

function userPhotoUrl(?array $user): ?string
{
    $photo = trim((string) ($user['photo'] ?? ''));
    if ($photo === '' || str_contains($photo, '..')) {
        return null;
    }

    if (!str_starts_with($photo, '/uploads/avatars/')) {
        return null;
    }

    return $photo;
}

/** @return array<string, string> */
function eventModalidadeOptions(): array
{
    return [
        'livre' => 'Livre',
        '4_jogadores_leste' => '4 Jogadores Leste',
        '4_jogadores_sul' => '4 Jogadores Sul',
        '3_jogadores_leste' => '3 Jogadores Leste',
        '3_jogadores_sul' => '3 Jogadores Sul',
    ];
}

function eventModalidadeLabel(?string $value): string
{
    $options = eventModalidadeOptions();
    $key = (string) ($value ?? '');

    return $options[$key] ?? $options['livre'];
}

/** Modalidades de mesa usadas no registro de partida. @return array<string, string> */
function matchFormatOptions(): array
{
    $options = eventModalidadeOptions();
    unset($options['livre']);

    return $options;
}

function matchFormatSeatCount(string $formato): int
{
    return str_starts_with($formato, '3_') ? 3 : 4;
}

/** @return array<string, string> */
function levelLabels(): array
{
    return [
        'iniciante' => 'Iniciante',
        'intermediario' => 'Intermediário',
        'avancado' => 'Avançado',
    ];
}

/** Formata a data de criação da conta sem hora (ex.: 11/11/2026). */
function formatMemberSince(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Exception) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        if ($parsed === false) {
            return '';
        }
        $date = $parsed;
    }

    return $date->format('d/m/Y');
}
