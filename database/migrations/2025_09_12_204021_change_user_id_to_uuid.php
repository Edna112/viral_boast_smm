<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if UUID column exists, if not add it
        if (!Schema::hasColumn('users', 'uuid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        // Generate UUIDs for existing users
        $users = \App\Models\User::all();
        foreach ($users as $user) {
            if (empty($user->uuid)) {
                $user->uuid = Str::uuid();
                $user->save();
            }
        }

        // Make the UUID column not nullable and unique (only if not already unique)
        $indexes = \DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_uuid_unique'");
        if (empty($indexes)) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->unique()->change();
            });
        } else {
            // Just make it not nullable if unique already exists
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->change();
            });
        }

        // Update foreign key references in other tables
        $this->updateForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove UUID column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }

    /**
     * Update foreign key references to use UUIDs
     */
    private function updateForeignKeys(): void
    {
        // Update user_memberships table
        if (Schema::hasTable('user_memberships')) {
            // Check if foreign key exists before dropping
            $foreignKeys = \DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'user_memberships' AND CONSTRAINT_NAME = 'user_memberships_user_id_foreign'");
            if (!empty($foreignKeys)) {
                Schema::table('user_memberships', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            }
            
            if (Schema::hasColumn('user_memberships', 'user_id')) {
                Schema::table('user_memberships', function (Blueprint $table) {
                    $table->dropColumn('user_id');
                });
            }

            Schema::table('user_memberships', function (Blueprint $table) {
                $table->uuid('user_uuid')->after('id');
            });

            // Migrate data
            $userMemberships = \DB::table('user_memberships')->get();
            foreach ($userMemberships as $membership) {
                $user = \App\Models\User::find($membership->user_id);
                if ($user) {
                    \DB::table('user_memberships')
                        ->where('id', $membership->id)
                        ->update(['user_uuid' => $user->uuid]);
                }
            }

            Schema::table('user_memberships', function (Blueprint $table) {
                $table->uuid('user_uuid')->nullable(false)->change();
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }

        // Update user_vip_memberships table
        if (Schema::hasTable('user_vip_memberships')) {
            // Check if foreign key exists before dropping
            $foreignKeys = \DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'user_vip_memberships' AND CONSTRAINT_NAME = 'user_vip_memberships_user_id_foreign'");
            if (!empty($foreignKeys)) {
                Schema::table('user_vip_memberships', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            }
            
            if (Schema::hasColumn('user_vip_memberships', 'user_id')) {
                Schema::table('user_vip_memberships', function (Blueprint $table) {
                    $table->dropColumn('user_id');
                });
            }

            Schema::table('user_vip_memberships', function (Blueprint $table) {
                $table->uuid('user_uuid')->after('id');
            });

            // Migrate data
            $userVipMemberships = \DB::table('user_vip_memberships')->get();
            foreach ($userVipMemberships as $membership) {
                $user = \App\Models\User::find($membership->user_id);
                if ($user) {
                    \DB::table('user_vip_memberships')
                        ->where('id', $membership->id)
                        ->update(['user_uuid' => $user->uuid]);
                }
            }

            Schema::table('user_vip_memberships', function (Blueprint $table) {
                $table->uuid('user_uuid')->nullable(false)->change();
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }

        // Update task_assignments table
        if (Schema::hasTable('task_assignments')) {
            // Check if foreign key exists before dropping
            $foreignKeys = \DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'task_assignments' AND CONSTRAINT_NAME = 'task_assignments_user_id_foreign'");
            if (!empty($foreignKeys)) {
                Schema::table('task_assignments', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            }
            
            if (Schema::hasColumn('task_assignments', 'user_id')) {
                Schema::table('task_assignments', function (Blueprint $table) {
                    $table->dropColumn('user_id');
                });
            }

            Schema::table('task_assignments', function (Blueprint $table) {
                $table->uuid('user_uuid')->after('id');
            });

            // Migrate data
            $taskAssignments = \DB::table('task_assignments')->get();
            foreach ($taskAssignments as $assignment) {
                $user = \App\Models\User::find($assignment->user_id);
                if ($user) {
                    \DB::table('task_assignments')
                        ->where('id', $assignment->id)
                        ->update(['user_uuid' => $user->uuid]);
                }
            }

            Schema::table('task_assignments', function (Blueprint $table) {
                $table->uuid('user_uuid')->nullable(false)->change();
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }

        // Update referrals table
        if (Schema::hasTable('referrals')) {
            // Check if foreign keys exist before dropping
            $referrerFk = \DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'referrals' AND CONSTRAINT_NAME = 'referrals_referrer_id_foreign'");
            $referredFk = \DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'referrals' AND CONSTRAINT_NAME = 'referrals_referred_user_id_foreign'");
            
            if (!empty($referrerFk)) {
                Schema::table('referrals', function (Blueprint $table) {
                    $table->dropForeign(['referrer_id']);
                });
            }
            if (!empty($referredFk)) {
                Schema::table('referrals', function (Blueprint $table) {
                    $table->dropForeign(['referred_user_id']);
                });
            }
            
            if (Schema::hasColumn('referrals', 'referrer_id') || Schema::hasColumn('referrals', 'referred_user_id')) {
                Schema::table('referrals', function (Blueprint $table) {
                    $table->dropColumn(['referrer_id', 'referred_user_id']);
                });
            }

            Schema::table('referrals', function (Blueprint $table) {
                $table->uuid('referrer_uuid')->after('id');
                $table->uuid('referred_user_uuid')->after('referrer_uuid');
            });

            // Migrate data
            $referrals = \DB::table('referrals')->get();
            foreach ($referrals as $referral) {
                $referrer = \App\Models\User::find($referral->referrer_id);
                $referredUser = \App\Models\User::find($referral->referred_user_id);
                
                if ($referrer && $referredUser) {
                    \DB::table('referrals')
                        ->where('id', $referral->id)
                        ->update([
                            'referrer_uuid' => $referrer->uuid,
                            'referred_user_uuid' => $referredUser->uuid
                        ]);
                }
            }

            Schema::table('referrals', function (Blueprint $table) {
                $table->uuid('referrer_uuid')->nullable(false)->change();
                $table->uuid('referred_user_uuid')->nullable(false)->change();
                $table->foreign('referrer_uuid')->references('uuid')->on('users')->onDelete('cascade');
                $table->foreign('referred_user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }
    }
};