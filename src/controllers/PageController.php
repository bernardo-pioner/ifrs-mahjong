<?php

declare(strict_types=1);

final class PageController extends Controller
{
    public function comingSoon(string $path, string $title, string $lede): void
    {
        $user = $this->requireAuth();
        $this->view('coming-soon', [
            'title' => $title,
            'heading' => $title,
            'lede' => $lede,
            'user' => $user,
            'currentPath' => $path,
        ]);
    }

    public function configuracoes(): void
    {
        $this->requireAdmin();
        $this->comingSoon('/configuracoes', 'Configurações', 'Preferências do clube e do sistema.');
    }

    public function app(): void
    {
        $this->requireAdmin();
        $this->comingSoon('/app', 'Área do app', 'A área pública do aplicativo ainda será construída.');
    }

    public function redirectAmistosos(): void
    {
        $this->requireAuth();
        redirect('/partidas');
    }

    public function redirectProgresso(): void
    {
        $this->requireAuth();
        redirect('/rankings');
    }
}
