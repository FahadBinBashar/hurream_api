<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Investor specific data remains embedded in the customers table.
        // This migration intentionally performs no action while preserving
        // the original ordering for existing pipelines.
    }

    public function down(): void
    {
        // Nothing to rollback because no schema changes occur in this migration.
    }
};
