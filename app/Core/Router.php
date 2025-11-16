<?php

namespace App\Core;

use Closure;

class Router
{
    protected array $routes = [];
    protected array $middlewareGroups = [];
    protected array $currentGroupMiddleware = [];
    protected string $currentPrefix = '';

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->currentPrefix;
        $previousMiddleware = $this->currentGroupMiddleware;

        $this->currentPrefix = rtrim($previousPrefix . '/' . ltrim($prefix, '/'), '/');
        $this->currentGroupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->currentPrefix = $previousPrefix;
        $this->currentGroupMiddleware = $previousMiddleware;
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     */
    public function add(string $method, string $uri, callable|array $action, array $middleware = []): void
    {
        $path = '/' . trim($this->currentPrefix . '/' . ltrim($uri, '/'), '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $path,
            'action' => $action,
            'middleware' => array_merge($this->currentGroupMiddleware, $middleware),
        ];
    }

    public function match(Request $request): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $route['uri']) . '$#';
            if (preg_match($pattern, $request->path(), $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [
                    'action' => $route['action'],
                    'middleware' => $route['middleware'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }
}
