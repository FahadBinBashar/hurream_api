<?php

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

class RoleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        $user = Auth::user();
        if (!$user) {
            return new Response(['message' => 'Unauthenticated'], 401);
        }

        if ($parameter && strtolower($user['role'] ?? '') !== strtolower($parameter)) {
            return new Response(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
