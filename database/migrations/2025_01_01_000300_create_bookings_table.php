<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate();
            $table->string('room_type');
            $table->unsignedInteger('guest_count');
            $table->dateTime('check_in');
            $table->dateTime('check_out');
            $table->string('price_plan');
            $table->string('payment_method');
            $table->decimal('amount', 10, 2);
            $table->string('payment_status')->default('pending');
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
