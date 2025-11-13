<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $queries = [
                "ALTER TABLE leads ADD COLUMN next_follow_up_at TIMESTAMP NULL",
                "ALTER TABLE leads ADD COLUMN last_contacted_at TIMESTAMP NULL",
                "ALTER TABLE leads ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'lead'",
                "ALTER TABLE leads ADD COLUMN source VARCHAR(100) NULL",
                "ALTER TABLE leads ADD COLUMN interest_level VARCHAR(50) NULL"
            ];
        } else {
            $queries = [
                "ALTER TABLE leads ADD COLUMN next_follow_up_at TEXT NULL",
                "ALTER TABLE leads ADD COLUMN last_contacted_at TEXT NULL",
                "ALTER TABLE leads ADD COLUMN status TEXT NOT NULL DEFAULT 'lead'",
                "ALTER TABLE leads ADD COLUMN source TEXT NULL",
                "ALTER TABLE leads ADD COLUMN interest_level TEXT NULL"
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
