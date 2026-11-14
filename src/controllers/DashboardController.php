<?php

declare(strict_types=1);

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $page = max(1, (int) request('page', '1'));
        $notificacoes = Notification::recentFor($user, $page);
        if ($page > $notificacoes['pages']) {
            redirect($notificacoes['pages'] <= 1 ? '/' : '/?page=' . $notificacoes['pages']);
        }

        if (isAdmin($user)) {
            $this->view('dashboard', [
                'title' => 'Dashboard',
                'user' => $user,
                'currentPath' => '/',
                'stats' => [
                    'members' => Member::count(),
                    'events' => Event::count(),
                    'matches' => GameMatch::count(),
                    'progress' => ProgressNote::count(),
                ],
                'notificacoes' => $notificacoes,
            ]);
            return;
        }

        $this->view('dashboard-member', [
            'title' => 'Início',
            'user' => $user,
            'currentPath' => '/',
            'stats' => [
                'events' => Event::count(),
                'matches' => GameMatch::count(),
                'progress' => ProgressNote::count(),
            ],
            'notificacoes' => $notificacoes,
        ]);
    }

    public function history(): void
    {
        $user = $this->requireAuth();
        $tipo = request('tipo');
        $estado = request('estado');
        $tipos = Notification::typeOptions($user);
        if (!array_key_exists($tipo, $tipos)) {
            $tipo = '';
        }
        if (!in_array($estado, ['ativa', 'dispensada'], true)) {
            $estado = '';
        }
        $page = max(1, (int) request('page', '1'));

        $this->view('notifications/history', [
            'title' => 'Histórico de notificações',
            'user' => $user,
            'currentPath' => '/notificacoes',
            'tipos' => $tipos,
            'tipoFiltro' => $tipo,
            'estadoFiltro' => $estado,
            'historico' => Notification::history($user, $tipo, $estado, $page),
        ]);
    }

    public function dismiss(): void
    {
        $user = $this->requireAuth();
        verifyCsrf();

        Notification::dismiss($user, request('chave'));

        $retorno = request('retorno', '/');
        $retornoValido = $retorno === '/'
            || preg_match('#^/\?page=[1-9]\d*$#', $retorno) === 1
            || $retorno === '/notificacoes'
            || str_starts_with($retorno, '/notificacoes?');
        if (!$retornoValido || str_contains($retorno, '//') || str_contains($retorno, '\\')) {
            $retorno = '/';
        }

        redirect($retorno);
    }
}
