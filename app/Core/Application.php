<?php

namespace App\Core;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Closure;
use RuntimeException;

class Application
{
    protected Router $router;
    protected array $middlewareRegistry = [];

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->registerDefaultMiddleware();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        $match = $this->router->match($request);
        if (!$match) {
            return new Response(['message' => 'Not Found'], 404);
        }

        $action = $match['action'];
        $params = $match['params'];
        $middleware = $match['middleware'];

        $handler = function ($request) use ($action, $params) {
            if (is_array($action)) {
                [$class, $method] = $action;
                $controller = new $class();
                $result = $controller->$method($request, $params);
            } else {
                $result = $action($request, $params);
            }

            if ($result instanceof Response) {
                return $result;
            }

            return new Response($result ?? []);
        };

        foreach (array_reverse($middleware) as $middlewareName) {
            $handler = $this->wrapMiddleware($middlewareName, $handler);
        }

        return $handler($request);
    }

    protected function wrapMiddleware(string $middlewareName, callable $next): callable
    {
        $name = $middlewareName;
        $parameter = null;
        if (str_contains($middlewareName, ':')) {
            [$name, $parameter] = explode(':', $middlewareName, 2);
        }

        if (!array_key_exists($name, $this->middlewareRegistry)) {
            throw new RuntimeException("Middleware {$name} is not registered");
        }

        $middlewareClass = $this->middlewareRegistry[$name];

        return function ($request) use ($middlewareClass, $next, $parameter) {
            $middleware = new $middlewareClass();
            return $middleware->handle($request, $next, $parameter);
        };
    }

    protected function registerDefaultMiddleware(): void
    {
        $this->middlewareRegistry = [
            'auth' => AuthMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ];
    }
}
