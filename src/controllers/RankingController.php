<?php

declare(strict_types=1);

final class RankingController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $this->view('progress/index', [
            'title' => 'Rankings',
            'user' => $user,
            'currentPath' => '/rankings',
        ]);
    }
}
