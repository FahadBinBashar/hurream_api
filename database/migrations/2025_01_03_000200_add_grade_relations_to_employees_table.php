<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable()->after('qualifications')->constrained('grades')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('grade_id')->constrained('designations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['designation_id']);
            $table->dropColumn(['grade_id', 'designation_id']);
        });
    }
};
