<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('package_id')->nullable()->constrained('share_packages')->nullOnDelete();
            $table->string('sale_type', 20); // single or package
            $table->unsignedInteger('total_shares');
            $table->unsignedInteger('bonus_shares')->default(0);
            $table->decimal('share_price', 12, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->unsignedInteger('installment_months')->default(0);
            $table->decimal('installment_amount', 15, 2)->default(0);
            $table->string('payment_mode', 30);
            $table->string('invoice_no')->unique();
            $table->string('certificate_no');
            $table->unsignedBigInteger('certificate_start');
            $table->unsignedBigInteger('certificate_end');
            $table->string('status', 20)->default('completed');
            $table->string('sale_source', 30)->default('api');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('share_sale_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_sale_id')->constrained('share_sales')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('share_batches')->cascadeOnUpdate();
            $table->unsignedInteger('shares_deducted');
            $table->unsignedBigInteger('certificate_from');
            $table->unsignedBigInteger('certificate_to');
            $table->decimal('share_price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_sale_batches');
        Schema::dropIfExists('share_sales');
    }
};
