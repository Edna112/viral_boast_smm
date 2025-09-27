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
        // First, drop foreign key constraints that reference the users table
        $this->dropUserForeignKeys();
        
        // Clear related tables to avoid foreign key constraint issues
        $this->clearRelatedTables();
        
        // Drop and recreate the users table with the new schema
        Schema::dropIfExists('users');
        
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name', 50);
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('password');
            $table->string('referral_code')->unique()->nullable();
            $table->uuid('referred_by')->nullable();
            $table->integer('total_tasks')->default(0);
            $table->integer('total_completed_today')->default(0);
            $table->string('profile_picture')->default('');
            $table->uuid('membership_level')->nullable();
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->boolean('isActive')->default(true);
            $table->timestamp('lastLogin')->nullable();
            $table->timestamps();
            
            // Add indexes for better query performance
            $table->index('email');
            $table->index('phone');
            $table->index('referral_code');
            $table->index('referred_by');
            $table->index('membership_level');
            $table->index('created_at');
        });

        // Recreate foreign key constraints
        $this->recreateUserForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints
        $this->dropUserForeignKeys();
        
        // Recreate the original users table structure
        Schema::dropIfExists('users');
        
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_image')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('email_verification_code')->nullable();
            $table->timestamp('email_verification_expires_at')->nullable();
            $table->string('phone_verification_code')->nullable();
            $table->timestamp('phone_verification_expires_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('referral_code')->unique();
            $table->string('referred_by')->nullable();
            $table->decimal('total_points', 12, 2)->default(0);
            $table->integer('tasks_completed_today')->default(0);
            $table->date('last_task_reset_date')->nullable();
            $table->rememberToken();
            $table->decimal('account_balance', 12, 2)->default(0);
            $table->boolean('is_admin')->default(false);
            $table->integer('total_tasks')->default(0);
            $table->integer('max_direct_referrals')->default(10);
            $table->integer('direct_referrals_count')->default(0);
            $table->integer('indirect_referrals_count')->default(0);
            $table->decimal('referral_bonus_earned', 10, 2)->default(0.00);
            $table->decimal('direct_referral_bonus', 10, 2)->default(5.00);
            $table->decimal('indirect_referral_bonus', 10, 2)->default(2.50);
            $table->timestamps();
        });
        
        // Recreate foreign key constraints
        $this->recreateUserForeignKeys();
    }

    /**
     * Clear related tables to avoid foreign key constraint issues
     */
    private function clearRelatedTables(): void
    {
        // Drop all foreign keys first
        $this->dropAllForeignKeys();
        
        $tables = [
            'task_submissions',
            'complaints', 
            'user_memberships',
            'accounts',
            'referrals',
            'task_assignments',
            'user_vip_memberships'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
    }

    /**
     * Drop all foreign keys in the application tables
     */
    private function dropAllForeignKeys(): void
    {
        $tables = [
            'task_submissions',
            'complaints', 
            'user_memberships',
            'accounts',
            'referrals',
            'task_assignments',
            'user_vip_memberships'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                // Get all foreign key constraints for this table
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_NAME = ? AND CONSTRAINT_NAME IS NOT NULL
                ", [$table]);
                
                foreach ($constraints as $constraint) {
                    try {
                        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
                    } catch (Exception $e) {
                        // Foreign key might not exist, continue
                    }
                }
            }
        }
    }

    /**
     * Drop foreign key constraints that reference the users table
     */
    private function dropUserForeignKeys(): void
    {
        // Only drop foreign keys from our application tables
        $tables = [
            'task_submissions' => ['user_uuid', 'reviewed_by'],
            'complaints' => ['user_uuid', 'assigned_to'],
            'user_memberships' => ['user_uuid'],
            'accounts' => ['user_uuid'],
            'referrals' => ['referrer_uuid', 'referred_user_uuid'],
            'task_assignments' => ['user_uuid'],
            'user_vip_memberships' => ['user_uuid']
        ];

        foreach ($tables as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    try {
                        // Get the actual foreign key constraint name
                        $constraints = DB::select("
                            SELECT CONSTRAINT_NAME 
                            FROM information_schema.KEY_COLUMN_USAGE 
                            WHERE TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = 'users'
                        ", [$table, $column]);
                        
                        foreach ($constraints as $constraint) {
                            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
                        }
                    } catch (Exception $e) {
                        // Foreign key might not exist, continue
                    }
                }
            }
        }
        
        // Also drop the self-referencing foreign key
        try {
            DB::statement("ALTER TABLE `users` DROP FOREIGN KEY `users_referred_by_foreign`");
        } catch (Exception $e) {
            // Foreign key might not exist, continue
        }
    }

    /**
     * Recreate foreign key constraints that reference the users table
     */
    private function recreateUserForeignKeys(): void
    {
        if (Schema::hasTable('task_submissions')) {
            Schema::table('task_submissions', function (Blueprint $table) {
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
                $table->foreign('reviewed_by')->references('uuid')->on('users')->onDelete('set null');
            });
        }

        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('set null');
                $table->foreign('assigned_to')->references('uuid')->on('users')->onDelete('set null');
            });
        }

        if (Schema::hasTable('user_memberships')) {
            Schema::table('user_memberships', function (Blueprint $table) {
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('accounts')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('referrals')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->foreign('referrer_uuid')->references('uuid')->on('users')->onDelete('cascade');
                $table->foreign('referred_user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }
    }
};