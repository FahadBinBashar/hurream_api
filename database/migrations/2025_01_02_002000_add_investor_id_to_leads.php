<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $column = $driver === 'sqlite' ? 'INTEGER' : 'BIGINT';
        try {
            $pdo->exec('ALTER TABLE leads ADD COLUMN investor_id ' . $column . ' NULL');
        } catch (\PDOException $e) {
        }
    }
};
