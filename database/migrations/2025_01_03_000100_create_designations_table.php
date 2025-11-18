<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->string('designation_name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['grade_id', 'designation_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
