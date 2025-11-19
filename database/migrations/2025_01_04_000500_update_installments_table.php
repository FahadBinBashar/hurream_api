<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('related_id')->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('notes');
        });
    }
};
