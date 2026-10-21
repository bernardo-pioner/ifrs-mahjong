<?php

declare(strict_types=1);

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->map('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->map('POST', $path, $handler);
    }

    //FUNCTION DISPATCH FOI ALTERADA PARA PODER CRIAR ROTAS ESPECÍFICAS PARA ALTERAÇÃO E EXCLUSÃO DE EVENTOS.

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize($uri);

        // Primeiro procura uma rota exata.
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler !== null) {
            $handler();
            return;
        }

        // Depois procura rotas com parâmetros dinâmicos.
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_quote($route, '#');
            $pattern = preg_replace(
                '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/',
                '(?P<$1>[0-9]+)',
                $pattern
            );

            if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
                $params = [];

                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[] = $value;
                    }
                }

                $handler(...$params);
                return;
            }
        }

        (new ErrorController())->notFound($path);
    }

    private function map(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$this->normalize($path)] = $handler;
    }

    private function normalize(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) && $parsed !== '' ? $parsed : '/';
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }
}
