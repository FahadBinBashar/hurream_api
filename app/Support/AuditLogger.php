<?php

namespace App\Support;

use App\Core\Database;
use App\Models\AuditLog;

class AuditLogger
{
    public static function log(?array $user, string $action, string $module, ?string $entityType = null, ?int $entityId = null, array $requestData = [], ?string $ip = null, ?string $userAgent = null): void
    {
        $payload = [
            'user_id' => $user['id'] ?? null,
            'role' => $user['role'] ?? null,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'request_data' => json_encode($requestData),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        AuditLog::create($payload);
    }

    public static function filter(array $filters = []): array
    {
        $pdo = Database::connection();
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $conditions[] = 'module = :module';
            $params['module'] = $filters['module'];
        }
        if (!empty($filters['from'])) {
            $conditions[] = 'created_at >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'created_at <= :to';
            $params['to'] = $filters['to'];
        }

        $sql = 'SELECT * FROM audit_logs';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
