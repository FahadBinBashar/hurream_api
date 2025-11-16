<?php

namespace App\Core;

use Illuminate\Support\Facades\Route;

class Router
{
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        Route::prefix(trim($prefix, '/'))
            ->middleware($middleware)
            ->group(function () use ($callback) {
                $callback($this);
            });
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     */
    public function add(string $method, string $uri, callable|array $action, array $middleware = []): void
    {
        $route = Route::match([strtolower($method)], ltrim($uri, '/'), function () use ($action) {
            $params = request()->route()?->parameters() ?? [];
            $request = app(Request::class);

            if (is_array($action)) {
                [$class, $method] = $action;
                $controller = app($class);

                return $controller->$method($request, $params);
            }

            return $action($request, $params);
        });

        if (!empty($middleware)) {
            $route->middleware($middleware);
        }
    }
}
