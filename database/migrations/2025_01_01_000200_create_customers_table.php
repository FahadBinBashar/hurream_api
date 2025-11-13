<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    NID VARCHAR(255) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NULL,
    reference VARCHAR(255) NULL,
    status VARCHAR(50) DEFAULT 'new',
    nid_document_path VARCHAR(255) NULL,
    photo_path VARCHAR(255) NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        } else {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    NID TEXT NOT NULL UNIQUE,
    address TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NULL,
    reference TEXT NULL,
    status TEXT DEFAULT 'new',
    nid_document_path TEXT NULL,
    photo_path TEXT NULL,
    verified_at TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL;
        }

        $pdo->exec($sql);
    }
};
