<?php

namespace App\Http\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

class PermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $parameter = null): Response
    {
        if (!$parameter) {
            return $next($request);
        }

        $user = Auth::user();
        if (!$user) {
            return new Response(['message' => 'Unauthenticated'], 401);
        }

        [$module, $action] = array_pad(explode('.', $parameter, 2), 2, 'read');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $user['role'] ?? '']);
        $role = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$role) {
            return new Response(['message' => 'Forbidden'], 403);
        }

        $permissionStmt = $pdo->prepare('SELECT id FROM permissions WHERE module = :module AND action = :action LIMIT 1');
        $permissionStmt->execute(['module' => $module, 'action' => $action]);
        $permission = $permissionStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$permission) {
            return $next($request);
        }

        $rolePermissionStmt = $pdo->prepare('SELECT allowed FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id LIMIT 1');
        $rolePermissionStmt->execute([
            'role_id' => $role['id'],
            'permission_id' => $permission['id'],
        ]);
        $rolePermission = $rolePermissionStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$rolePermission || (int)$rolePermission['allowed'] !== 1) {
            return new Response(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
