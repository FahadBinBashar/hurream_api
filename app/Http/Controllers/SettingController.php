<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Setting;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all();
        $pdo = Database::connection();
        $conditions = [];
        $params = [];
        if (!empty($filters['key'])) {
            $conditions[] = '`key` LIKE :key';
            $params['key'] = '%' . $filters['key'] . '%';
        }
        if (!empty($filters['type'])) {
            $conditions[] = 'type = :type';
            $params['type'] = $filters['type'];
        }

        $sql = 'SELECT * FROM settings';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY `key` ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function update(Request $request, array $params)
    {
        $key = $params['key'];
        if ($response = $this->validate($request, [
            'value' => 'required',
            'type' => 'in:string,int,bool,json',
            'description' => '',
        ])) {
            return $response;
        }

        $existing = $this->findByKey($key);
        $payload = $request->all();
        $payload['updated_by'] = Auth::user()['id'] ?? null;
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $pdo = Database::connection();
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE settings SET value = :value, type = :type, description = :description, updated_by = :updated_by, updated_at = :updated_at WHERE `key` = :key');
            $stmt->execute([
                'value' => $payload['value'],
                'type' => $payload['type'] ?? $existing['type'],
                'description' => $payload['description'] ?? $existing['description'],
                'updated_by' => $payload['updated_by'],
                'updated_at' => $payload['updated_at'],
                'key' => $key,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO settings (`key`, value, type, description, updated_by, updated_at, created_at) VALUES (:key, :value, :type, :description, :updated_by, :updated_at, :created_at)');
            $stmt->execute([
                'key' => $key,
                'value' => $payload['value'],
                'type' => $payload['type'] ?? 'string',
                'description' => $payload['description'] ?? null,
                'updated_by' => $payload['updated_by'],
                'updated_at' => $payload['updated_at'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        AuditLogger::log(Auth::user(), 'update', 'settings', 'setting', null, ['key' => $key, 'value' => $payload['value']], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Setting saved']);
    }

    protected function findByKey(string $key): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM settings WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);

        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $record ?: null;
    }
}
