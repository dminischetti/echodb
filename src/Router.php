<?php

declare(strict_types=1);

namespace App;

use Psr\Log\LoggerInterface;
use Throwable;

class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    private string $basePath;

    private ?LoggerInterface $logger;

    public function __construct(string $basePath = '', ?LoggerInterface $logger = null)
    {
        $normalized = trim($basePath);
        if ($normalized === '' || $normalized === '/') {
            $this->basePath = '';
            $this->logger = $logger;

            return;
        }

        $this->basePath = rtrim('/' . ltrim($normalized, '/'), '/');
        $this->logger = $logger;
    }

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
        $path = $this->stripBasePath($path);
        $path = rtrim($path, '/') ?: '/';

        if (!isset($this->routes[$method . $path])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found']);

            return;
        }

        $handler = $this->routes[$method . $path];

        try {
            $handler();

            return;
        } catch (Throwable $exception) {
            if ($this->logger !== null) {
                $this->logger->error(
                    'Unhandled exception during route dispatch.',
                    [
                        'method' => $method,
                        'path' => $path,
                        'exception' => $exception,
                    ]
                );
            }
        }

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal Server Error']);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $normalizedPath = rtrim($path, '/') ?: '/';
        $this->routes[$method . $normalizedPath] = $handler;
    }

    private function stripBasePath(string $path): string
    {
        if ($this->basePath === '') {
            return $path;
        }

        if ($path === $this->basePath || $path === $this->basePath . '/') {
            return '/';
        }

        if (str_starts_with($path, $this->basePath . '/')) {
            $stripped = substr($path, strlen($this->basePath));

            return $stripped === '' ? '/' : $stripped;
        }

        return $path;
    }
}
