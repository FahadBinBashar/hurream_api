<?php

namespace App\Http\Middleware;

use App\Core\Request;
use App\Support\Auth;
use Closure;
use Illuminate\Http\Request as IlluminateRequest;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(IlluminateRequest $illuminateRequest, Closure $next, ?string $parameter = null): Response
    {
        $request = Request::fromIlluminate($illuminateRequest);
        $user = Auth::user();
        if (!$user) {
            return new \App\Core\Response(['message' => 'Unauthenticated'], 401);
        }

        if ($parameter && strtolower($user['role'] ?? '') !== strtolower($parameter)) {
            return new \App\Core\Response(['message' => 'Forbidden'], 403);
        }

        return $next($illuminateRequest);
    }
}
