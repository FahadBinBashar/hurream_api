<?php

namespace App\Support;

use App\Core\Database;
use PDO;

class Auth
{
    protected static ?array $user = null;

    public static function user(): ?array
    {
        return static::$user;
    }

    public static function setUser(?array $user): void
    {
        static::$user = $user;
    }

    public static function attempt(string $email, string $password): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            static::setUser($user);
            return $user;
        }

        return null;
    }
}
