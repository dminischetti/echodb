<?php

declare(strict_types=1);

namespace App;

class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function options(string $path, callable $handler): void
    {
        $this->addRoute('OPTIONS', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        if (!isset($this->routes[$method . $path])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found']);

            return;
        }

        $handler = $this->routes[$method . $path];
        $handler();
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $normalizedPath = rtrim($path, '/') ?: '/';
        $this->routes[$method . $normalizedPath] = $handler;
    }
}
