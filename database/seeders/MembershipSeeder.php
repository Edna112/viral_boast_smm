<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Membership;

class MembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Basic membership
        Membership::create([
            'membership_name' => 'Basic',
            'description' => 'Basic membership with standard features and 5 tasks per day',
            'tasks_per_day' => 5,
            'max_tasks' => 100,
            'price' => 0.00,
            'benefit_amount_per_task' => 1.00,
            'is_active' => true,
        ]);

        // Create Premium membership
        Membership::create([
            'membership_name' => 'Premium',
            'description' => 'Premium membership with enhanced features and 15 tasks per day',
            'tasks_per_day' => 15,
            'max_tasks' => 300,
            'price' => 9.99,
            'benefit_amount_per_task' => 2.50,
            'is_active' => true,
        ]);

        // Create VIP membership
        Membership::create([
            'membership_name' => 'VIP',
            'description' => 'VIP membership with maximum features and unlimited tasks',
            'tasks_per_day' => 50,
            'max_tasks' => 1000,
            'price' => 29.99,
            'benefit_amount_per_task' => 5.00,
            'is_active' => true,
        ]);
    }
}

