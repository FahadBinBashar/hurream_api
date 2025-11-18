<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_name');
            $table->string('package_code')->unique();
            $table->decimal('package_price', 15, 2);
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->unsignedInteger('duration_months');
            $table->decimal('monthly_installment', 15, 2);
            $table->unsignedInteger('auto_share_units');
            $table->decimal('bonus_share_percent', 5, 2)->default(0);
            $table->unsignedInteger('bonus_share_units')->default(0);
            $table->unsignedInteger('free_nights')->default(0);
            $table->decimal('lifetime_discount', 5, 2)->default(0);
            $table->decimal('tour_voucher_value', 12, 2)->default(0);
            $table->string('gift_items')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_packages');
    }
};
