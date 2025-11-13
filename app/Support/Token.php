<?php

namespace App\Support;

use App\Core\Database;
use App\Models\PersonalAccessToken;

class Token
{
    public static function create(int $userId, string $name = 'api'): array
    {
        $plainText = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainText);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+12 hours'));

        PersonalAccessToken::create([
            'user_id' => $userId,
            'name' => $name,
            'token' => $hash,
            'abilities' => json_encode(['*']),
            'last_used_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainText,
            'expires_at' => $expiresAt,
        ];
    }

    public static function deleteForUser(int $userId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM personal_access_tokens WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
