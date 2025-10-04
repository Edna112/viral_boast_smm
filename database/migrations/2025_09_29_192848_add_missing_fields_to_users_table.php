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
            // Add fields in order, without specifying 'after' to avoid dependency issues
            
            // Basic fields that should exist
            if (!Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'email_verification_code')) {
                $table->string('email_verification_code')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'email_verification_expires_at')) {
                $table->timestamp('email_verification_expires_at')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'phone_verification_code')) {
                $table->string('phone_verification_code')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'phone_verification_expires_at')) {
                $table->timestamp('phone_verification_expires_at')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 10)->unique()->nullable();
            }
            
            if (!Schema::hasColumn('users', 'referred_by')) {
                $table->uuid('referred_by')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'total_points')) {
                $table->decimal('total_points', 12, 2)->default(0);
            }
            
            if (!Schema::hasColumn('users', 'total_tasks')) {
                $table->integer('total_tasks')->default(0);
            }
            
            if (!Schema::hasColumn('users', 'tasks_completed_today')) {
                $table->integer('tasks_completed_today')->default(0);
            }
            
            if (!Schema::hasColumn('users', 'last_task_reset_date')) {
                $table->date('last_task_reset_date')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'account_balance')) {
                $table->decimal('account_balance', 12, 2)->default(0);
            }
            
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false);
            }
            
            if (!Schema::hasColumn('users', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'deactivation_reason')) {
                $table->text('deactivation_reason')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'profile_visibility')) {
                $table->enum('profile_visibility', ['public', 'private', 'friends'])->default('public');
            }
            
            if (!Schema::hasColumn('users', 'show_email')) {
                $table->boolean('show_email')->default(false);
            }
            
            if (!Schema::hasColumn('users', 'show_phone')) {
                $table->boolean('show_phone')->default(false);
            }
            
            if (!Schema::hasColumn('users', 'show_activity')) {
                $table->boolean('show_activity')->default(true);
            }
            
            if (!Schema::hasColumn('users', 'email_notifications')) {
                $table->boolean('email_notifications')->default(true);
            }
            
            if (!Schema::hasColumn('users', 'sms_notifications')) {
                $table->boolean('sms_notifications')->default(false);
            }
            
            // Add the fields that were in the original fillable but missing from DB
            if (!Schema::hasColumn('users', 'membership_level')) {
                $table->integer('membership_level')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user');
            }
            
            if (!Schema::hasColumn('users', 'isActive')) {
                $table->boolean('isActive')->default(true);
            }
            
            if (!Schema::hasColumn('users', 'lastLogin')) {
                $table->timestamp('lastLogin')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'membership_level',
                'role',
                'isActive',
                'lastLogin',
                'total_points',
                'profile_image',
                'email_verification_code',
                'email_verification_expires_at',
                'phone_verification_code',
                'phone_verification_expires_at',
                'phone_verified_at',
                'referral_code',
                'referred_by',
                'total_tasks',
                'tasks_completed_today',
                'last_task_reset_date',
                'account_balance',
                'is_active',
                'is_admin',
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