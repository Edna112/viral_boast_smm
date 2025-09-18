<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FixDuplicateUuidsSeeder extends Seeder
{
    public function run(): void
    {
        // Get all users and regenerate UUIDs to ensure uniqueness
        $users = User::all();
        
        foreach ($users as $user) {
            $user->uuid = Str::uuid();
            $user->save();
        }
        
        $this->command->info("✅ Regenerated UUIDs for {$users->count()} users");
    }
}

