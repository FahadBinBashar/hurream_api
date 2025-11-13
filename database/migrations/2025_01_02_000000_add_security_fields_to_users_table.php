<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $queries = [
                "ALTER TABLE users ADD COLUMN otp_code VARCHAR(255) NULL",
                "ALTER TABLE users ADD COLUMN otp_expires_at TIMESTAMP NULL",
                "ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0",
                "ALTER TABLE users ADD COLUMN two_factor_type VARCHAR(50) NULL"
            ];
        } else {
            $queries = [
                "ALTER TABLE users ADD COLUMN otp_code TEXT NULL",
                "ALTER TABLE users ADD COLUMN otp_expires_at TEXT NULL",
                "ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER NOT NULL DEFAULT 0",
                "ALTER TABLE users ADD COLUMN two_factor_type TEXT NULL"
            ];
        }

        foreach ($queries as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                // Ignore if column already exists
            }
        }
    }
};
