<?php

return new class {
    public function up(\PDO $pdo): void
    {
        // Investor specific data is now part of the customers table.
        // This migration is intentionally left blank to preserve order
        // for existing deployments.
    }
};
