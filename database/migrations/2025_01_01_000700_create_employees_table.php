<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS employees (
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
            status TEXT DEFAULT "active",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
    }
};
