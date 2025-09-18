<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserModelSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        
        if ($user) {
            $this->command->info("✅ User Model Test:");
            $this->command->info("   ID: {$user->id}");
            $this->command->info("   UUID: {$user->uuid}");
            $this->command->info("   Name: {$user->name}");
            $this->command->info("   Email: {$user->email}");
            
            // Test if UUID is being used as primary key
            $this->command->info("   Primary Key: {$user->getKey()}");
            $this->command->info("   Key Name: {$user->getKeyName()}");
        } else {
            $this->command->error("❌ No users found");
        }
    }
}

