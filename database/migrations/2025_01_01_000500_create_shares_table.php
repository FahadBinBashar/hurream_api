<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate();
            $table->string('share_type', 20)->default('single');
            $table->foreignId('package_id')->nullable()->constrained('share_packages')->nullOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('share_units');
            $table->unsignedInteger('bonus_units')->default(0);
            $table->unsignedInteger('total_units');
            $table->decimal('amount', 15, 2);
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->decimal('monthly_installment', 15, 2)->nullable();
            $table->unsignedInteger('installment_months')->nullable();
            $table->string('payment_mode', 20);
            $table->string('stage');
            $table->boolean('reinvest_flag')->default(false);
            $table->string('status', 30)->default('active');
            $table->string('approval_status')->default('approved');
            $table->boolean('approver_gate_triggered')->default(false);
            $table->json('benefits_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_shares');
    }
};
