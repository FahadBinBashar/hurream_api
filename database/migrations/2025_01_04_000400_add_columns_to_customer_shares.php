<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_shares', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->after('project_id')->constrained('share_sales')->nullOnDelete();
            $table->foreignId('primary_batch_id')->nullable()->after('sale_id')->constrained('share_batches')->nullOnDelete();
            $table->string('certificate_no')->nullable()->after('primary_batch_id');
            $table->string('invoice_no')->nullable()->after('certificate_no');
        });
    }

    public function down(): void
    {
        Schema::table('customer_shares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('sale_id');
            $table->dropConstrainedForeignId('primary_batch_id');
            $table->dropColumn(['certificate_no', 'invoice_no']);
        });
    }
};
