<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $queries = [
                "ALTER TABLE accounts ADD COLUMN code VARCHAR(100) NULL AFTER id",
                "ALTER TABLE accounts ADD COLUMN name VARCHAR(255) NULL AFTER code",
                "ALTER TABLE accounts ADD COLUMN category VARCHAR(50) NULL AFTER name",
                "ALTER TABLE accounts ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER category",
                "ALTER TABLE accounts ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER parent_id"
            ];
        } else {
            $queries = [
                "ALTER TABLE accounts ADD COLUMN code TEXT NULL",
                "ALTER TABLE accounts ADD COLUMN name TEXT NULL",
                "ALTER TABLE accounts ADD COLUMN category TEXT NULL",
                "ALTER TABLE accounts ADD COLUMN parent_id INTEGER NULL",
                "ALTER TABLE accounts ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1"
            ];
        }

        foreach ($queries as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
            }
        }
    }
};
