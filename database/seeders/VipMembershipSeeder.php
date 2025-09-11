<?php

namespace Database\Seeders;

use App\Models\Membership;
use Illuminate\Database\Seeder;

class VipMembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $memberships = [
            [
                'membership_name' => 'Basic',
                'description' => 'Basic membership with standard rewards',
                'price' => 0.00,
                'benefits' => 'Standard task rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'reward_multiplier' => 1.0,
                'priority_level' => 1,
                'is_active' => true,
            ],
            [
                'membership_name' => 'VIP1',
                'description' => 'VIP1 membership with 1.5x rewards',
                'price' => 9.99,
                'benefits' => '1.5x task rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'reward_multiplier' => 1.5,
                'priority_level' => 2,
                'is_active' => true,
            ],
            [
                'membership_name' => 'VIP2',
                'description' => 'VIP2 membership with 2x rewards',
                'price' => 19.99,
                'benefits' => '2x task rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'reward_multiplier' => 2.0,
                'priority_level' => 3,
                'is_active' => true,
            ],
            [
                'membership_name' => 'VIP3',
                'description' => 'VIP3 membership with 2.5x rewards',
                'price' => 29.99,
                'benefits' => '2.5x task rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'reward_multiplier' => 2.5,
                'priority_level' => 4,
                'is_active' => true,
            ],
            [
                'membership_name' => 'VIP4',
                'description' => 'VIP4 membership with 3x rewards',
                'price' => 39.99,
                'benefits' => '3x task rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'reward_multiplier' => 3.0,
                'priority_level' => 5,
                'is_active' => true,
            ],
            [
                'membership_name' => 'VIP5',
                'description' => 'VIP5 membership with 3.5x rewards',
                'price' => 49.99,
                'benefits' => '3.5x task rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'reward_multiplier' => 3.5,
                'priority_level' => 6,
                'is_active' => true,
            ],
            [
                'membership_name' => 'VIP6',
                'description' => 'VIP6 membership with 4x rewards',
                'price' => 59.99,
                'benefits' => '4x task rewards',
                'tasks_per_day' => 1,
                'max_tasks' => 1,
                'reward_multiplier' => 4.0,
                'priority_level' => 7,
                'is_active' => true,
            ],
        ];

        foreach ($memberships as $membershipData) {
            Membership::updateOrCreate(
                ['membership_name' => $membershipData['membership_name']],
                $membershipData
            );
        }
    }
}
