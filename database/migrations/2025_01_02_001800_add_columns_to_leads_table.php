<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('interest_level', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'next_follow_up_at',
                'last_contacted_at',
                'source',
                'interest_level',
            ]);
        });
    }
};
