<?php

namespace App\Core;

use PDO;

class Migrator
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->ensureMigrationTable();
    }

    public function migrate(): void
    {
        $files = glob(dirname(__DIR__, 1) . '/../database/migrations/*.php');
        sort($files);
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($this->isMigrated($name)) {
                continue;
            }

            $migration = require $file;
            $migration->up($this->pdo);
            $this->markMigrated($name);
        }
    }

    protected function ensureMigrationTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, migrated_at TEXT)');
    }

    protected function isMigrated(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM migrations WHERE name = :name');
        $stmt->execute(['name' => $name]);
        return $stmt->fetchColumn() > 0;
    }

    protected function markMigrated(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO migrations (name, migrated_at) VALUES (:name, :migrated_at)');
        $stmt->execute(['name' => $name, 'migrated_at' => date('Y-m-d H:i:s')]);
    }
}
