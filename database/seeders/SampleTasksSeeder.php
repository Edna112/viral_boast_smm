<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Seeder;

class SampleTasksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tasks = [
            [
                'title' => 'Like Instagram Post',
                'description' => 'Like our latest Instagram post and show proof',
                'platform' => 'Instagram',
                'instructions' => '1. Go to our Instagram page 2. Find the latest post 3. Like the post 4. Take a screenshot showing the like',
                'target_url' => 'https://instagram.com/yourpage',
                'base_points' => 10,
                'estimated_duration_minutes' => 2,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Share Facebook Post',
                'description' => 'Share our Facebook post to your timeline',
                'platform' => 'Facebook',
                'instructions' => '1. Go to our Facebook page 2. Find the latest post 3. Share the post to your timeline 4. Take a screenshot of the shared post',
                'target_url' => 'https://facebook.com/yourpage',
                'base_points' => 15,
                'estimated_duration_minutes' => 3,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Subscribe YouTube Channel',
                'description' => 'Subscribe to our YouTube channel',
                'platform' => 'YouTube',
                'instructions' => '1. Go to our YouTube channel 2. Click the subscribe button 3. Turn on notifications 4. Take a screenshot showing subscription',
                'target_url' => 'https://youtube.com/yourchannel',
                'base_points' => 20,
                'estimated_duration_minutes' => 2,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Follow Twitter Account',
                'description' => 'Follow our Twitter account',
                'platform' => 'Twitter',
                'instructions' => '1. Go to our Twitter profile 2. Click the follow button 3. Take a screenshot showing you are following',
                'target_url' => 'https://twitter.com/yourhandle',
                'base_points' => 8,
                'estimated_duration_minutes' => 1,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Join Telegram Group',
                'description' => 'Join our Telegram group',
                'platform' => 'Telegram',
                'instructions' => '1. Open Telegram 2. Search for our group 3. Join the group 4. Take a screenshot showing you are in the group',
                'target_url' => 'https://t.me/yourgroup',
                'base_points' => 12,
                'estimated_duration_minutes' => 2,
                'requires_photo' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($tasks as $taskData) {
            Task::updateOrCreate(
                ['title' => $taskData['title']],
                $taskData
            );
        }
    }
}
