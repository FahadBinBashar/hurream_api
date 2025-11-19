<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('batch_name');
            $table->decimal('share_price', 12, 2);
            $table->unsignedBigInteger('total_shares');
            $table->unsignedBigInteger('available_shares');
            $table->unsignedBigInteger('certificate_start_no');
            $table->unsignedBigInteger('certificate_end_no');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_batches');
    }
};
