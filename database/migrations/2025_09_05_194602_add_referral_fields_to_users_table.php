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
            // Only add referral_code if it doesn't exist
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 10)->unique()->nullable()->after('email_verification_expires_at');
            }
            // Only add referred_by if it doesn't exist
            if (!Schema::hasColumn('users', 'referred_by')) {
                $table->uuid('referred_by')->nullable()->after('referral_code');
            }
            // Only add foreign key if it doesn't exist
            $foreignKeys = \DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'users_referred_by_foreign'");
            if (empty($foreignKeys)) {
                $table->foreign('referred_by')->references('uuid')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop foreign key if it exists
            $foreignKeys = \DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'users_referred_by_foreign'");
            if (!empty($foreignKeys)) {
                $table->dropForeign(['referred_by']);
            }
            // Only drop columns if they exist
            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
            if (Schema::hasColumn('users', 'referred_by')) {
                $table->dropColumn('referred_by');
            }
        });
    }
};
