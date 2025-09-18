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
        // Generate unique UUIDs for existing users
        $users = \App\Models\User::all();
        foreach ($users as $user) {
            if (empty($user->uuid)) {
                do {
                    $uuid = Str::uuid();
                } while (\App\Models\User::where('uuid', $uuid)->exists());
                
                $user->uuid = $uuid;
                $user->save();
            }
        }

        // Add unique constraint to UUID column if it doesn't exist
        $indexes = \DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_uuid_unique'");
        if (empty($indexes)) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });
    }
};