<?php
/** @var string $title */
/** @var string $content */
/** @var string $appName */
/** @var array|null $user */
/** @var array|null $flash */
/** @var string $currentPath */
$isGuest = $user === null;
$isAdmin = isAdmin($user);
$brandRole = $isAdmin ? 'Admin' : (is_array($user) ? (string) ($user['name'] ?? 'Participante') : 'Participante');
$navItems = [
    ['href' => '/', 'label' => 'Dashboard', 'icon' => 'icon-dashboard', 'adminOnly' => false],
    ['href' => '/torneios', 'label' => 'Torneios', 'icon' => 'icon-trophy', 'adminOnly' => false],
    ['href' => '/eventos', 'label' => 'Eventos', 'icon' => 'icon-calendar', 'adminOnly' => false],
    ['href' => '/partidas', 'label' => 'Partidas', 'icon' => 'icon-layers', 'adminOnly' => false],
    ['href' => '/participantes', 'label' => 'Participantes', 'icon' => 'icon-users', 'adminOnly' => false],
    ['href' => '/rankings', 'label' => 'Rankings', 'icon' => 'icon-chart', 'adminOnly' => false],
    ['href' => '/noticias', 'label' => 'Notícias', 'icon' => 'icon-news', 'adminOnly' => false],
    ['href' => '/usuarios', 'label' => 'Usuários', 'icon' => 'icon-user-search', 'adminOnly' => true],
    ['href' => '/configuracoes', 'label' => 'Configurações', 'icon' => 'icon-sliders', 'adminOnly' => true],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="<?= $isGuest ? 'is-guest' : 'is-app' ?>">
    <?php if ($isGuest): ?>
        <div class="shell">
            <header class="guest-brand">
                <a class="brand" href="/login">
                    <span class="brand-mark" aria-hidden="true">M</span>
                    <span class="brand-text">Mahjong</span>
                </a>
            </header>
            <?php if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <main class="main">
                <?= $content ?>
            </main>
        </div>
    <?php else: ?>
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="/">
                <span class="brand-mark" aria-hidden="true">M</span>
                <span class="brand-copy">
                    <span class="brand-text">Mahjong</span>
                    <span class="brand-role<?= $isAdmin ? '' : ' is-user-name' ?>"><?= e($brandRole) ?></span>
                </span>
            </a>

            <nav id="main-nav" class="nav">
                <?php foreach ($navItems as $item): ?>
                    <?php if ($item['adminOnly'] && !$isAdmin) {
                        continue;
                    } ?>
                    <a href="<?= e($item['href']) ?>" class="<?= isActivePath($currentPath, $item['href']) ? 'is-active' : '' ?>">
                        <span class="icon <?= e($item['icon']) ?>" aria-hidden="true"></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-foot">
                <?php if ($isAdmin): ?>
                    <a href="/app">
                        <span class="icon icon-back" aria-hidden="true"></span>
                        <span>Voltar ao app</span>
                    </a>
                <?php endif; ?>
                <form method="post" action="/logout">
                    <?= csrfField() ?>
                    <button type="submit" class="sidebar-logout">
                        <span class="icon icon-logout" aria-hidden="true"></span>
                        <span>Sair</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="workspace">
            <header class="workspace-bar">
                <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="sidebar">
                    <span class="icon icon-menu" aria-hidden="true"></span>
                    Menu
                </button>
                <span class="session-name"><?= e($user['name']) ?></span>
            </header>

            <?php if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <main class="main">
                <?= $content ?>
            </main>
        </div>
        <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <?php endif; ?>
    <script src="/assets/js/app.js"></script>
</body>
</html>
