<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('max_direct_referrals')->default(10)->after('total_tasks');
            $table->integer('direct_referrals_count')->default(0)->after('max_direct_referrals');
            $table->integer('indirect_referrals_count')->default(0)->after('direct_referrals_count');
            $table->decimal('referral_bonus_earned', 10, 2)->default(0.00)->after('indirect_referrals_count');
            $table->decimal('direct_referral_bonus', 10, 2)->default(5.00)->after('referral_bonus_earned');
            $table->decimal('indirect_referral_bonus', 10, 2)->default(2.50)->after('direct_referral_bonus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'max_direct_referrals',
                'direct_referrals_count',
                'indirect_referrals_count',
                'referral_bonus_earned',
                'direct_referral_bonus',
                'indirect_referral_bonus'
            ]);
        });
    }
};