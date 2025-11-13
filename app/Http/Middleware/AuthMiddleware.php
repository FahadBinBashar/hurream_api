<?php

namespace App\Http\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use PDO;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        $header = $request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return new Response(['message' => 'Unauthenticated'], 401);
        }

        $token = substr($header, 7);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT t.*, u.* FROM personal_access_tokens t JOIN users u ON u.id = t.user_id WHERE t.token = :token AND (t.expires_at IS NULL OR t.expires_at > :now) LIMIT 1');
        $stmt->execute([
            'token' => hash('sha256', $token),
            'now' => date('Y-m-d H:i:s'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return new Response(['message' => 'Invalid token'], 401);
        }

        Auth::setUser($row);

        return $next($request);
    }
}
