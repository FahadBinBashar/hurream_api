<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('police_verification_path')->nullable();
            $table->timestamp('police_verified_at')->nullable();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('nid_document_path')->nullable();
            $table->string('police_verification_path')->nullable();
            $table->timestamp('nid_verified_at')->nullable();
            $table->timestamp('police_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['police_verification_path', 'police_verified_at']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'nid_document_path',
                'police_verification_path',
                'nid_verified_at',
                'police_verified_at',
            ]);
        });
    }
};
