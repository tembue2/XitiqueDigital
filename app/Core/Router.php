<?php

declare(strict_types=1);

namespace Xitique\Core;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable|array{0: string, 1: string}}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler');
    }

    public function dispatch(string $method, string $path): mixed
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);

            if ($params === null) {
                continue;
            }

            $handler = $route['handler'];

            if (is_array($handler)) {
                [$class, $methodName] = $handler;
                $handler = [new $class(), $methodName];
            }

            return $handler(...$params);
        }

        Response::error('Rota não encontrada.', 404);
    }

    /** @return array<int, mixed>|null */
    private function match(string $pattern, string $path): ?array
    {
        $paramNames = [];
        $regex = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)}/', static function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];

            return '([^/]+)';
        }, $pattern);

        if ($regex === null) {
            return null;
        }

        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }

        array_shift($matches);

        return array_map(static function (string $value): int|string {
            return ctype_digit($value) ? (int) $value : urldecode($value);
        }, $matches);
    }
}

