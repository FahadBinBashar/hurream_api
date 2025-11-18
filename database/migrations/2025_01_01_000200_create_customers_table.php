<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('NID')->unique();
            $table->text('address');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default('new');
            $table->string('membership_type')->default('general');
            $table->boolean('is_investor')->default(true);
            $table->string('investor_no')->nullable()->unique();
            $table->text('bank_info')->nullable();
            $table->string('nominee')->nullable();
            $table->unsignedBigInteger('created_by_employee_id')->nullable();
            $table->string('nid_document_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('police_verification_path')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('police_verified_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('admin_approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
