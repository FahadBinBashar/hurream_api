<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'police_verification_path')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('police_verification_path')->nullable();
            });
        }

        if (!Schema::hasColumn('customers', 'police_verified_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('police_verified_at')->nullable();
            });
        }

        if (!Schema::hasColumn('employees', 'nid_document_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('nid_document_path')->nullable();
            });
        }

        if (!Schema::hasColumn('employees', 'police_verification_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('police_verification_path')->nullable();
            });
        }

        if (!Schema::hasColumn('employees', 'nid_verified_at')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->timestamp('nid_verified_at')->nullable();
            });
        }

        if (!Schema::hasColumn('employees', 'police_verified_at')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->timestamp('police_verified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'police_verification_path')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('police_verification_path');
            });
        }

        if (Schema::hasColumn('customers', 'police_verified_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('police_verified_at');
            });
        }

        $employeeColumns = collect([
            'nid_document_path',
            'police_verification_path',
            'nid_verified_at',
            'police_verified_at',
        ])->filter(fn (string $column) => Schema::hasColumn('employees', $column))
            ->all();

        if ($employeeColumns) {
            Schema::table('employees', function (Blueprint $table) use ($employeeColumns) {
                $table->dropColumn($employeeColumns);
            });
        }
    }
};
