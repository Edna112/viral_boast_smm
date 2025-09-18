<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CheckAndFixUuidsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        
        $this->command->info("📋 Current Users and UUIDs:");
        foreach ($users as $user) {
            $this->command->info("ID: {$user->id} - UUID: {$user->uuid} - Name: {$user->name}");
        }
        
        // Check for duplicates
        $uuids = $users->pluck('uuid')->toArray();
        $duplicates = array_diff_assoc($uuids, array_unique($uuids));
        
        if (!empty($duplicates)) {
            $this->command->error("❌ Found duplicate UUIDs: " . implode(', ', $duplicates));
            
            // Fix duplicates
            foreach ($users as $user) {
                if (in_array($user->uuid, $duplicates)) {
                    $user->uuid = Str::uuid();
                    $user->save();
                    $this->command->info("✅ Fixed UUID for user {$user->id}: {$user->uuid}");
                }
            }
        } else {
            $this->command->info("✅ All UUIDs are unique");
        }
    }
}

