<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Task;
use App\Models\Membership;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminAndTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User (if not exists)
        $admin = User::firstOrCreate(
            ['email' => 'admin@viralboast.com'],
            [
                'name' => 'Admin User',
                'phone' => '+1234567890',
                'password' => Hash::make('admin123'),
                'referral_code' => 'ADMIN001',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Admin user created:');
        $this->command->info('   Email: admin@viralboast.com');
        $this->command->info('   Password: admin123');
        $this->command->info('   Referral Code: ADMIN001');

        // Create Basic Membership (if not exists)
        $basicMembership = Membership::firstOrCreate(
            ['membership_name' => 'Basic'],
            [
                'userid' => 'system',
                'description' => 'Basic membership with standard rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'price' => 9.99,
                'reward_multiplier' => 1.0,
                'priority_level' => 1,
                'is_active' => true,
            ]
        );

        // Create VIP Membership (if not exists)
        $vipMembership = Membership::firstOrCreate(
            ['membership_name' => 'VIP'],
            [
                'userid' => 'system',
                'description' => 'VIP membership with 2x rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'price' => 19.99,
                'reward_multiplier' => 2.0,
                'priority_level' => 2,
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Memberships created:');
        $this->command->info('   Basic: $9.99/month (1 task/day, 1x rewards)');
        $this->command->info('   VIP: $19.99/month (1 task/day, 2x rewards)');

        // Create Task Categories
        $categories = [
            [
                'name' => 'Social Media',
                'slug' => 'social-media',
                'description' => 'Social media engagement tasks (Instagram, Facebook, Twitter, etc.)',
            ],
            [
                'name' => 'Content Creation',
                'slug' => 'content-creation',
                'description' => 'Content creation tasks (Blog posts, videos, graphics, etc.)',
            ],
            [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'description' => 'Marketing and promotional tasks (Email campaigns, ads, etc.)',
            ],
            [
                'name' => 'Research',
                'slug' => 'research',
                'description' => 'Research and data collection tasks (Surveys, analysis, etc.)',
            ],
            [
                'name' => 'E-commerce',
                'slug' => 'ecommerce',
                'description' => 'E-commerce related tasks (Product reviews, store visits, etc.)',
            ],
            [
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General tasks that don\'t fit other categories',
            ]
        ];

        $createdCategories = [];
        foreach($categories as $catData) {
            $category = \App\Models\TaskCategory::firstOrCreate(
                ['name' => $catData['name']],
                [
                    'slug' => $catData['slug'],
                    'description' => $catData['description'],
                    'is_active' => true,
                ]
            );
            $createdCategories[] = $category;
        }

        $this->command->info('✅ Task categories created:');
        foreach($createdCategories as $cat) {
            $this->command->info("   {$cat->id} - {$cat->name}");
        }

        // Create Sample Tasks with different categories
        $tasks = [
            [
                'title' => 'Follow Instagram Account',
                'description' => 'Follow the specified Instagram account and like 3 recent posts',
                'category_id' => $createdCategories[0]->id, // Social Media
                'task_type' => 'follow',
                'platform' => 'instagram',
                'instructions' => '1. Click the follow button\n2. Like 3 recent posts\n3. Take a screenshot as proof',
                'target_url' => 'https://instagram.com/example_account',
                'reward' => 10,
                'task_status' => 'active',
                'estimated_duration_minutes' => 5,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Like Facebook Post',
                'description' => 'Like and share the specified Facebook post',
                'category_id' => $createdCategories[0]->id, // Social Media
                'task_type' => 'like',
                'platform' => 'facebook',
                'instructions' => '1. Like the post\n2. Share the post to your timeline\n3. Take a screenshot',
                'target_url' => 'https://facebook.com/posts/example',
                'reward' => 15,
                'task_status' => 'active',
                'estimated_duration_minutes' => 3,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Subscribe to YouTube Channel',
                'description' => 'Subscribe to the YouTube channel and watch the latest video',
                'category_id' => $createdCategories[0]->id, // Social Media
                'task_type' => 'subscribe',
                'platform' => 'youtube',
                'instructions' => '1. Subscribe to the channel\n2. Watch the latest video for at least 30 seconds\n3. Take a screenshot',
                'target_url' => 'https://youtube.com/channel/example',
                'reward' => 20,
                'task_status' => 'active',
                'estimated_duration_minutes' => 8,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Comment on Twitter Post',
                'description' => 'Comment on the specified Twitter post',
                'category_id' => $createdCategories[0]->id, // Social Media
                'task_type' => 'comment',
                'platform' => 'twitter',
                'instructions' => '1. Find the tweet\n2. Add a meaningful comment\n3. Take a screenshot',
                'target_url' => 'https://twitter.com/example/status/123',
                'reward' => 8,
                'task_status' => 'active',
                'estimated_duration_minutes' => 2,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Join Telegram Channel',
                'description' => 'Join the specified Telegram channel',
                'category_id' => $createdCategories[0]->id, // Social Media
                'task_type' => 'follow',
                'platform' => 'telegram',
                'instructions' => '1. Click the join link\n2. Join the channel\n3. Take a screenshot of the channel',
                'target_url' => 'https://t.me/example_channel',
                'reward' => 12,
                'task_status' => 'active',
                'estimated_duration_minutes' => 3,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($tasks as $taskData) {
            $task = Task::firstOrCreate(
                ['title' => $taskData['title']],
                $taskData
            );
            $this->command->info("✅ Task created: {$task->title} ({$task->base_points} points)");
        }

        $this->command->info('');
        $this->command->info('🎉 Database seeded successfully!');
        $this->command->info('');
        $this->command->info('📋 Summary:');
        $this->command->info('   • 1 Admin user created');
        $this->command->info('   • 2 Memberships created');
        $this->command->info('   • 5 Sample tasks created');
        $this->command->info('');
        $this->command->info('🔑 Admin Login:');
        $this->command->info('   Email: admin@viralboast.com');
        $this->command->info('   Password: admin123');
    }
}
