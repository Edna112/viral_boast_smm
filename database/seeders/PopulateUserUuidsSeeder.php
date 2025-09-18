<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PopulateUserUuidsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereNull('uuid')->orWhere('uuid', '')->get();
        
        foreach ($users as $user) {
            $user->uuid = Str::uuid();
            $user->save();
        }
        
        $this->command->info("✅ Populated UUIDs for {$users->count()} users");
    }
}

