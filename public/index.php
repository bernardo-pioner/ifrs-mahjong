<?php

declare(strict_types=1);

if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

$auth = new AuthController();
$dashboard = new DashboardController();
$events = new EventController();
$matches = new MatchController();
$members = new MemberController();
$rankings = new RankingController();
$users = new UserController();
$pages = new PageController();

$router = new Router();

$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
$router->get('/cadastro', [$auth, 'showRegister']);
$router->post('/cadastro', [$auth, 'register']);
$router->post('/logout', [$auth, 'logout']);

$router->get('/', [$dashboard, 'index']);
$router->get('/eventos', [$events, 'index']);
$router->get('/partidas', [$matches, 'index']);
$router->get('/participantes', [$members, 'index']);
$router->get('/rankings', [$rankings, 'index']);

$router->get('/torneios', [$pages, 'torneios']);
$router->get('/noticias', [$pages, 'noticias']);
$router->get('/usuarios', [$users, 'index']);
$router->get('/configuracoes', [$pages, 'configuracoes']);
$router->get('/app', [$pages, 'app']);

$router->get('/membros', [$pages, 'redirectMembros']);
$router->get('/amistosos', [$pages, 'redirectAmistosos']);
$router->get('/progresso', [$pages, 'redirectProgresso']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
