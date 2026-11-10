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
$rankings = new RankingController();
$users = new UserController();
$pages = new PageController();

$router = new Router();

$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
$router->get('/cadastro', [$auth, 'showRegister']);
$router->post('/cadastro', [$auth, 'register']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/username-disponivel', [$auth, 'checkUsernameAvailability']);
$router->get('/email-disponivel', [$auth, 'checkEmailAvailability']);

$router->get('/', [$dashboard, 'index']);
$router->get('/eventos', [$events, 'index']);
$router->get('/eventos/novo', [$events, 'create']);
$router->post('/eventos/novo', [$events, 'store']);
$router->get('/eventos/{id}/editar', [$events, 'edit']);
$router->post('/eventos/{id}/editar', [$events, 'update']);
$router->get('/eventos/{id}', [$events, 'show']);
$router->post('/eventos/{id}/excluir', [$events, 'destroy']);
$router->get('/partidas', [$matches, 'index']);
$router->get('/rankings', [$rankings, 'index']);

$router->get('/usuarios', [$users, 'index']);
$router->get('/usuarios/editar', [$users, 'edit']);
$router->post('/usuarios/editar', [$users, 'update']);
$router->post('/users/delete', [$users, 'delete']);
$router->get('/perfil', [$users, 'bio']);
$router->post('/perfil', [$users, 'updateBio']);
$router->get('/configuracoes', [$pages, 'configuracoes']);
$router->get('/app', [$pages, 'app']);

$router->get('/amistosos', [$pages, 'redirectAmistosos']);
$router->get('/progresso', [$pages, 'redirectProgresso']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
