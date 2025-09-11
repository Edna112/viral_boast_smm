<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create test tasks
        DB::table('task')->insert([
            [
                'task_name' => 'Follow Instagram Account',
                'task_type' => 'social_media',
                'task_url' => 'https://instagram.com/viralboast',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'task_name' => 'Share Post on Story',
                'task_type' => 'social_media', 
                'task_url' => 'https://instagram.com/viralboast/post/123',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Create test memberships
        DB::table('membership')->insert([
            [
                'userid' => 'basic_user',
                'membership_name' => 'Basic',
                'description' => 'Basic membership with standard rewards',
                'price' => 0,
                'benefits' => 1.0,
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'userid' => 'vip1_user',
                'membership_name' => 'VIP1',
                'description' => 'VIP1 membership with 1.5x rewards',
                'price' => 9.99,
                'benefits' => 1.5,
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        echo "Test data created successfully!\n";
    }
}
