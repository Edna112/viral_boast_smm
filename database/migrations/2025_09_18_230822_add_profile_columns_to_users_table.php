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
            // Profile picture
            $table->string('profile_picture')->nullable()->after('phone_verified_at');
            
            // Account status
            $table->boolean('is_active')->default(true)->after('profile_picture');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->text('deactivation_reason')->nullable()->after('deactivated_at');
            
            // Privacy settings
            $table->enum('profile_visibility', ['public', 'private', 'friends'])->default('public')->after('deactivation_reason');
            $table->boolean('show_email')->default(false)->after('profile_visibility');
            $table->boolean('show_phone')->default(false)->after('show_email');
            $table->boolean('show_activity')->default(true)->after('show_phone');
            
            // Notification preferences
            $table->boolean('email_notifications')->default(true)->after('show_activity');
            $table->boolean('sms_notifications')->default(false)->after('email_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_picture',
                'is_active',
                'deactivated_at',
                'deactivation_reason',
                'profile_visibility',
                'show_email',
                'show_phone',
                'show_activity',
                'email_notifications',
                'sms_notifications',
            ]);
        });
    }
};