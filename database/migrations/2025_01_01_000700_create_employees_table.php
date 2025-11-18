<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('father_name');
            $table->string('mother_name');
            $table->string('nid')->unique();
            $table->text('address');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('education');
            $table->text('qualifications')->nullable();
            $table->string('grade');
            $table->string('position');
            $table->date('join_date');
            $table->decimal('salary', 12, 2);
            $table->text('document_checklist')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
