<?php
/** @var string $title */
/** @var string $content */
/** @var string $appName */
/** @var array|null $user */
/** @var array|null $flash */
/** @var string $currentPath */
$isGuest = $user === null;
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
                    <span class="brand-role">Admin</span>
                </span>
            </a>

            <nav id="main-nav" class="nav">
                <a href="/" class="<?= isActivePath($currentPath, '/') ? 'is-active' : '' ?>">
                    <span class="icon icon-dashboard" aria-hidden="true"></span>
                    <span>Dashboard</span>
                </a>
                <a href="/torneios" class="<?= isActivePath($currentPath, '/torneios') ? 'is-active' : '' ?>">
                    <span class="icon icon-trophy" aria-hidden="true"></span>
                    <span>Torneios</span>
                </a>
                <a href="/eventos" class="<?= isActivePath($currentPath, '/eventos') ? 'is-active' : '' ?>">
                    <span class="icon icon-calendar" aria-hidden="true"></span>
                    <span>Eventos</span>
                </a>
                <a href="/partidas" class="<?= isActivePath($currentPath, '/partidas') ? 'is-active' : '' ?>">
                    <span class="icon icon-layers" aria-hidden="true"></span>
                    <span>Partidas</span>
                </a>
                <a href="/participantes" class="<?= isActivePath($currentPath, '/participantes') ? 'is-active' : '' ?>">
                    <span class="icon icon-users" aria-hidden="true"></span>
                    <span>Participantes</span>
                </a>
                <a href="/rankings" class="<?= isActivePath($currentPath, '/rankings') ? 'is-active' : '' ?>">
                    <span class="icon icon-chart" aria-hidden="true"></span>
                    <span>Rankings</span>
                </a>
                <a href="/noticias" class="<?= isActivePath($currentPath, '/noticias') ? 'is-active' : '' ?>">
                    <span class="icon icon-news" aria-hidden="true"></span>
                    <span>Notícias</span>
                </a>
                <a href="/usuarios" class="<?= isActivePath($currentPath, '/usuarios') ? 'is-active' : '' ?>">
                    <span class="icon icon-user-search" aria-hidden="true"></span>
                    <span>Usuários</span>
                </a>
                <a href="/configuracoes" class="<?= isActivePath($currentPath, '/configuracoes') ? 'is-active' : '' ?>">
                    <span class="icon icon-sliders" aria-hidden="true"></span>
                    <span>Configurações</span>
                </a>
            </nav>

            <div class="sidebar-foot">
                <a href="/app">
                    <span class="icon icon-back" aria-hidden="true"></span>
                    <span>Voltar ao app</span>
                </a>
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
