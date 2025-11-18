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
    membership_type VARCHAR(100) DEFAULT 'general',
    is_investor TINYINT(1) NOT NULL DEFAULT 1,
    investor_no VARCHAR(50) NULL UNIQUE,
    bank_info TEXT NULL,
    nominee VARCHAR(255) NULL,
    created_by_employee_id BIGINT UNSIGNED NULL,
    nid_document_path VARCHAR(255) NULL,
    photo_path VARCHAR(255) NULL,
    police_verification_path VARCHAR(255) NULL,
    verified_at TIMESTAMP NULL,
    police_verified_at TIMESTAMP NULL,
    otp_verified_at TIMESTAMP NULL,
    admin_approved_at TIMESTAMP NULL,
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
    membership_type TEXT DEFAULT 'general',
    is_investor INTEGER NOT NULL DEFAULT 1,
    investor_no TEXT NULL UNIQUE,
    bank_info TEXT NULL,
    nominee TEXT NULL,
    created_by_employee_id INTEGER NULL,
    nid_document_path TEXT NULL,
    photo_path TEXT NULL,
    police_verification_path TEXT NULL,
    verified_at TEXT NULL,
    police_verified_at TEXT NULL,
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
