<?php

declare(strict_types=1);

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();

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
        ]);
    }
}
