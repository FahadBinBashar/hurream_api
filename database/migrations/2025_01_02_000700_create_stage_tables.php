<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sequence');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('customer_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_stage_id')->constrained('stages');
            $table->decimal('capital_amount', 15, 2)->default(0);
            $table->boolean('reinvest_enabled')->default(true);
            $table->timestamp('last_closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stage_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_stage_id')->constrained('customer_stages')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('profit_amount', 15, 2)->default(0);
            $table->decimal('reinvest_amount', 15, 2)->default(0);
            $table->decimal('cashout_amount', 15, 2)->default(0);
            $table->decimal('next_capital_amount', 15, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_periods');
        Schema::dropIfExists('customer_stages');
        Schema::dropIfExists('stages');
    }
};
