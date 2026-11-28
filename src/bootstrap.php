<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const ROOT_PATH = __DIR__ . '/..';

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
    if (class_exists(Dotenv\Dotenv::class)) {
        Dotenv\Dotenv::createUnsafeImmutable(ROOT_PATH)->safeLoad();
    }
}

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
Event::cancelarInscricoesPendentesEncerradas();

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

function userRole(?array $user): string
{
    $role = (string) ($user['role'] ?? 'member');

    return in_array($role, ['admin', 'moderator', 'member'], true) ? $role : 'member';
}

function isAdmin(?array $user): bool
{
    return userRole($user) === 'admin';
}

function isModerator(?array $user): bool
{
    return userRole($user) === 'moderator';
}

function canOrganizeEvents(?array $user): bool
{
    $role = userRole($user);

    return $role === 'admin' || $role === 'moderator';
}

/** @param array<string, mixed> $evento */
function canManageEvent(?array $user, array $evento): bool
{
    if (isAdmin($user)) {
        return true;
    }

    if (!isModerator($user)) {
        return false;
    }

    return (int) ($evento['admin_id'] ?? 0) === (int) ($user['id'] ?? 0);
}

function roleLabel(?array $user): string
{
    return match (userRole($user)) {
        'admin' => 'Admin',
        'moderator' => 'Moderador',
        default => 'Membro',
    };
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

function appBaseUrl(): string
{
    $configured = getenv('APP_URL');
    if (is_string($configured) && trim($configured) !== '') {
        return rtrim(trim($configured), '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

    return ($https ? 'https' : 'http') . '://' . $host;
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

function formatEventDayDate(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Exception) {
        return '';
    }

    return $date->format('d/m/Y · H:i');
}

function formatEventDescription(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('/<\s*\/?\s*[a-z]/i', $value)) {
        return nl2br(e($value), false);
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML(
        '<?xml encoding="utf-8"><div>' . $value . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $document->getElementsByTagName('div')->item(0);
    if (!$root instanceof DOMElement) {
        return nl2br(e(strip_tags($value)), false);
    }

    sanitizeEventDescriptionNode($root);
    $html = '';
    foreach ($root->childNodes as $child) {
        $html .= $document->saveHTML($child);
    }

    return trim($html);
}

function eventDescriptionText(string $html): string
{
    $texto = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;
    $texto = preg_replace('/<\/(?:p|h1|h2|h3|div)>/i', "\n", $texto) ?? $texto;
    $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace("/[ \t]+/u", ' ', $texto) ?? $texto;
    $texto = preg_replace("/\n{3,}/u", "\n\n", $texto) ?? $texto;

    return trim($texto);
}

function sanitizeEventDescriptionNode(DOMNode $node): void
{
    $allowed = [
        'p' => true,
        'br' => true,
        'strong' => true,
        'b' => true,
        'em' => true,
        'i' => true,
        'u' => true,
        'h1' => true,
        'h2' => true,
        'h3' => true,
    ];
    $drop = [
        'script' => true,
        'style' => true,
        'iframe' => true,
        'object' => true,
        'embed' => true,
        'link' => true,
        'meta' => true,
    ];

    $children = [];
    foreach ($node->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($child->nodeName);
        if (isset($drop[$tag])) {
            $node->removeChild($child);
            continue;
        }

        if ($tag === 'div') {
            $paragraph = $child->ownerDocument->createElement('p');
            while ($child->firstChild) {
                $paragraph->appendChild($child->firstChild);
            }
            $node->replaceChild($paragraph, $child);
            $child = $paragraph;
            $tag = 'p';
        }

        sanitizeEventDescriptionNode($child);

        if (!isset($allowed[$tag])) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        while ($child->attributes->length > 0) {
            $attribute = $child->attributes->item(0);
            if ($attribute !== null) {
                $child->removeAttribute($attribute->name);
            }
        }

        $texto = str_replace("\u{200b}", '', $child->textContent ?? '');
        if ($tag !== 'br' && trim($texto) === '') {
            $node->removeChild($child);
        }
    }
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
