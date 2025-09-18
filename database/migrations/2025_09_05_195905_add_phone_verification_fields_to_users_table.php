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
            // Only add columns if they don't exist
            if (!Schema::hasColumn('users', 'phone_verification_code')) {
                $table->string('phone_verification_code')->nullable()->after('email_verification_expires_at');
            }
            if (!Schema::hasColumn('users', 'phone_verification_expires_at')) {
                $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_code');
            }
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('phone_verification_expires_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns if they exist
            if (Schema::hasColumn('users', 'phone_verification_code')) {
                $table->dropColumn('phone_verification_code');
            }
            if (Schema::hasColumn('users', 'phone_verification_expires_at')) {
                $table->dropColumn('phone_verification_expires_at');
            }
            if (Schema::hasColumn('users', 'phone_verified_at')) {
                $table->dropColumn('phone_verified_at');
            }
        });
    }
};
