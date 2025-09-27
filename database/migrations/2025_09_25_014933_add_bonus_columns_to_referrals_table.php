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
        Schema::table('referrals', function (Blueprint $table) {
            $table->enum('referral_type', ['direct', 'indirect'])->default('direct')->after('referred_user_uuid');
            $table->decimal('bonus_amount', 10, 2)->default(0.00)->after('referral_type');
            $table->timestamp('bonus_paid_at')->nullable()->after('bonus_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['referral_type', 'bonus_amount', 'bonus_paid_at']);
        });
    }
};