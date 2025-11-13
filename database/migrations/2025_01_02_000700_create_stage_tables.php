<?php

return new class {
    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stagesSql = <<<SQL
CREATE TABLE IF NOT EXISTS stages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    sequence INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
            $investorStagesSql = <<<SQL
CREATE TABLE IF NOT EXISTS investor_stages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    investor_id BIGINT UNSIGNED NOT NULL,
    current_stage_id BIGINT UNSIGNED NOT NULL,
    capital_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    reinvest_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_closed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_investor_stage_investor FOREIGN KEY (investor_id) REFERENCES investors(id) ON DELETE CASCADE,
    CONSTRAINT fk_investor_stage_stage FOREIGN KEY (current_stage_id) REFERENCES stages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
            $stagePeriodsSql = <<<SQL
CREATE TABLE IF NOT EXISTS stage_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    investor_stage_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    profit_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    reinvest_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    cashout_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    next_capital_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stage_period_investor_stage FOREIGN KEY (investor_stage_id) REFERENCES investor_stages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        } else {
            $stagesSql = <<<SQL
CREATE TABLE IF NOT EXISTS stages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NULL,
    sequence INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL;
            $investorStagesSql = <<<SQL
CREATE TABLE IF NOT EXISTS investor_stages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    investor_id INTEGER NOT NULL,
    current_stage_id INTEGER NOT NULL,
    capital_amount REAL NOT NULL DEFAULT 0,
    reinvest_enabled INTEGER NOT NULL DEFAULT 1,
    last_closed_at TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL;
            $stagePeriodsSql = <<<SQL
CREATE TABLE IF NOT EXISTS stage_periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    investor_stage_id INTEGER NOT NULL,
    period_start TEXT NOT NULL,
    period_end TEXT NOT NULL,
    profit_amount REAL NOT NULL DEFAULT 0,
    reinvest_amount REAL NOT NULL DEFAULT 0,
    cashout_amount REAL NOT NULL DEFAULT 0,
    next_capital_amount REAL NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL;
        }

        $pdo->exec($stagesSql);
        $pdo->exec($investorStagesSql);
        $pdo->exec($stagePeriodsSql);
    }
};
