<?php

declare(strict_types=1);

final class EventController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $this->view('events/index', [
            'title' => 'Eventos',
            'user' => $user,
            'currentPath' => '/eventos',
        ]);
    }
}
