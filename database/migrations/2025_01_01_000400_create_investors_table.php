<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS investors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    NID VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    bank_info TEXT NULL,
    nominee VARCHAR(255) NULL,
    status VARCHAR(50) DEFAULT 'pending',
    otp_verified_at TIMESTAMP NULL,
    admin_approved_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        } else {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS investors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    NID TEXT NOT NULL UNIQUE,
    phone TEXT NOT NULL,
    email TEXT NOT NULL,
    address TEXT NOT NULL,
    bank_info TEXT NULL,
    nominee TEXT NULL,
    status TEXT DEFAULT 'pending',
    otp_verified_at TEXT NULL,
    admin_approved_at TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL;
        }

        $pdo->exec($sql);
    }
};
