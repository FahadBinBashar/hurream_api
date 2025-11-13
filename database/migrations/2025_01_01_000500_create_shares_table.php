<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            investor_id INTEGER NOT NULL,
            unit_price REAL NOT NULL,
            quantity INTEGER NOT NULL,
            amount REAL NOT NULL,
            payment_mode TEXT NOT NULL,
            stage TEXT NOT NULL,
            reinvest_flag INTEGER DEFAULT 0,
            approval_status TEXT DEFAULT "approved",
            approver_gate_triggered INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(investor_id) REFERENCES investors(id)
        )');
    }
};
