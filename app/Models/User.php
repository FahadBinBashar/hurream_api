<?php

namespace App\Models;

use App\Core\Database;

class User extends BaseModel
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $record ?: null;
    }
}
