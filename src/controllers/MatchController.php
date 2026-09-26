<?php

declare(strict_types=1);

final class MatchController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $this->view('matches/index', [
            'title' => 'Partidas',
            'user' => $user,
            'currentPath' => '/partidas',
        ]);
    }
}
