<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $customerQueries = [
                "ALTER TABLE customers ADD COLUMN police_verification_path VARCHAR(255) NULL",
                "ALTER TABLE customers ADD COLUMN police_verified_at TIMESTAMP NULL"
            ];
            $employeeQueries = [
                "ALTER TABLE employees ADD COLUMN nid_document_path VARCHAR(255) NULL",
                "ALTER TABLE employees ADD COLUMN police_verification_path VARCHAR(255) NULL",
                "ALTER TABLE employees ADD COLUMN nid_verified_at TIMESTAMP NULL",
                "ALTER TABLE employees ADD COLUMN police_verified_at TIMESTAMP NULL"
            ];
        } else {
            $customerQueries = [
                "ALTER TABLE customers ADD COLUMN police_verification_path TEXT NULL",
                "ALTER TABLE customers ADD COLUMN police_verified_at TEXT NULL"
            ];
            $employeeQueries = [
                "ALTER TABLE employees ADD COLUMN nid_document_path TEXT NULL",
                "ALTER TABLE employees ADD COLUMN police_verification_path TEXT NULL",
                "ALTER TABLE employees ADD COLUMN nid_verified_at TEXT NULL",
                "ALTER TABLE employees ADD COLUMN police_verified_at TEXT NULL"
            ];
        }

        foreach (array_merge($customerQueries, $employeeQueries) as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
            }
        }
    }
};
