<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('share_packages')->cascadeOnDelete();
            $table->string('benefit_type');
            $table->string('benefit_value');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_benefits');
    }
};
