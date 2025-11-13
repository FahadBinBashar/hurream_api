<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            NID TEXT NOT NULL UNIQUE,
            address TEXT NOT NULL,
            phone TEXT NOT NULL,
            email TEXT NULL,
            reference TEXT NULL,
            status TEXT DEFAULT "new",
            nid_document_path TEXT NULL,
            photo_path TEXT NULL,
            verified_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
    }
};
