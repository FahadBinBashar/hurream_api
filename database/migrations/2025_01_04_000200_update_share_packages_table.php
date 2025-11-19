<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_packages', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedInteger('total_shares_included')->default(0)->after('package_price');
            $table->unsignedInteger('bonus_shares')->default(0)->after('total_shares_included');
            $table->unsignedInteger('installment_months')->default(0)->after('bonus_shares');
            $table->json('benefits')->nullable()->after('installment_months');
            $table->text('description')->nullable()->change();
        });

        Schema::table('share_packages', function (Blueprint $table) {
            if (Schema::hasColumn('share_packages', 'duration_months')) {
                $table->dropColumn('duration_months');
            }
            if (Schema::hasColumn('share_packages', 'monthly_installment')) {
                $table->dropColumn('monthly_installment');
            }
            if (Schema::hasColumn('share_packages', 'auto_share_units')) {
                $table->dropColumn('auto_share_units');
            }
            if (Schema::hasColumn('share_packages', 'bonus_share_percent')) {
                $table->dropColumn('bonus_share_percent');
            }
            if (Schema::hasColumn('share_packages', 'bonus_share_units')) {
                $table->dropColumn('bonus_share_units');
            }
            if (Schema::hasColumn('share_packages', 'free_nights')) {
                $table->dropColumn('free_nights');
            }
            if (Schema::hasColumn('share_packages', 'lifetime_discount')) {
                $table->dropColumn('lifetime_discount');
            }
            if (Schema::hasColumn('share_packages', 'tour_voucher_value')) {
                $table->dropColumn('tour_voucher_value');
            }
            if (Schema::hasColumn('share_packages', 'gift_items')) {
                $table->dropColumn('gift_items');
            }
        });
    }

    public function down(): void
    {
        Schema::table('share_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['total_shares_included', 'bonus_shares', 'installment_months', 'benefits']);
            $table->integer('duration_months')->default(0);
            $table->decimal('monthly_installment', 15, 2)->default(0);
            $table->unsignedInteger('auto_share_units')->default(0);
            $table->decimal('bonus_share_percent', 5, 2)->default(0);
            $table->unsignedInteger('bonus_share_units')->default(0);
            $table->unsignedInteger('free_nights')->default(0);
            $table->decimal('lifetime_discount', 5, 2)->default(0);
            $table->decimal('tour_voucher_value', 12, 2)->default(0);
            $table->string('gift_items')->nullable();
        });
    }
};
