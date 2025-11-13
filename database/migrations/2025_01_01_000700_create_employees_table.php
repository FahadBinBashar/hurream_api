<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    father_name VARCHAR(255) NOT NULL,
    mother_name VARCHAR(255) NOT NULL,
    nid VARCHAR(255) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NULL,
    education VARCHAR(255) NOT NULL,
    qualifications TEXT NULL,
    grade VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    join_date DATE NOT NULL,
    salary DECIMAL(12, 2) NOT NULL,
    document_checklist TEXT NULL,
    photo_path VARCHAR(255) NULL,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        } else {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    father_name TEXT NOT NULL,
    mother_name TEXT NOT NULL,
    nid TEXT NOT NULL UNIQUE,
    address TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NULL,
    education TEXT NOT NULL,
    qualifications TEXT NULL,
    grade TEXT NOT NULL,
    position TEXT NOT NULL,
    join_date TEXT NOT NULL,
    salary REAL NOT NULL,
    document_checklist TEXT NULL,
    photo_path TEXT NULL,
    status TEXT DEFAULT 'active',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL;
        }

        $pdo->exec($sql);
    }
};
