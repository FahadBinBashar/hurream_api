<?php

namespace App\Models;

use App\Core\Database;
use PDO;

abstract class BaseModel
{
    protected static string $table;

    public static function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM ' . static::$table);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM ' . static::$table . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ?: null;
    }

    public static function create(array $data): array
    {
        $pdo = Database::connection();
        $keys = array_keys($data);
        $columns = implode(', ', $keys);
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, $keys));
        $stmt = $pdo->prepare('INSERT INTO ' . static::$table . ' (' . $columns . ') VALUES (' . $placeholders . ')');
        $stmt->execute($data);
        $id = (int)$pdo->lastInsertId();
        return static::find($id);
    }

    public static function update(int $id, array $data): ?array
    {
        if (empty($data)) {
            return static::find($id);
        }

        $pdo = Database::connection();
        $assignments = implode(', ', array_map(fn($k) => $k . '=:' . $k, array_keys($data)));
        $data['id'] = $id;
        $stmt = $pdo->prepare('UPDATE ' . static::$table . ' SET ' . $assignments . ' WHERE id = :id');
        $stmt->execute($data);
        return static::find($id);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM ' . static::$table . ' WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
