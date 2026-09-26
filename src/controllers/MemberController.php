<?php

declare(strict_types=1);

final class MemberController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $this->view('members/index', [
            'title' => 'Participantes',
            'user' => $user,
            'currentPath' => '/participantes',
        ]);
    }
}
