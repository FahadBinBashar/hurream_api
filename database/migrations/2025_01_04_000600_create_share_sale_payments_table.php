<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_sale_id')->constrained('share_sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate();
            $table->decimal('amount', 15, 2);
            $table->string('payment_channel', 50);
            $table->string('receipt_no')->unique();
            $table->string('reference_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('gateway')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_sale_payments');
    }
};
