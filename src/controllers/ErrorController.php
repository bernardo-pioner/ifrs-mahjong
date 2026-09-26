<?php

declare(strict_types=1);

final class ErrorController extends Controller
{
    public function notFound(string $path): void
    {
        http_response_code(404);
        $this->view('errors/404', [
            'title' => 'Página não encontrada',
            'user' => AuthService::user(),
            'currentPath' => $path,
        ]);
    }
}
