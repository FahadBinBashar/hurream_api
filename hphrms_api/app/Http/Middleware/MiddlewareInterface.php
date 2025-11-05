<?php

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response;
}
