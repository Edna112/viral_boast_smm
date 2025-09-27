<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Membership;
use App\Models\UserMembership;
use App\Models\Account;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating Admin Users...');

        // Create Basic membership if it doesn't exist
        $basicMembership = Membership::firstOrCreate(
            ['membership_name' => 'Basic'],
            [
                'description' => 'Basic membership for regular users',
                'tasks_per_day' => 5,
                'max_tasks' => 100,
                'task_link' => 'https://example.com/basic',
                'benefits' => 1.0,
                'price' => 0.00,
                'reward_multiplier' => 1.0,
                'priority_level' => 1,
                'is_active' => true,
                'daily_task_limit' => 5,
                'max_tasks_per_distribution' => 3,
                'distribution_priority' => 1,
            ]
        );

        // Create Admin membership
        $adminMembership = Membership::firstOrCreate(
            ['membership_name' => 'Admin'],
            [
                'description' => 'Administrator membership with full access',
                'tasks_per_day' => 1000,
                'max_tasks' => 100000,
                'task_link' => 'https://admin.viralboast.com',
                'benefits' => 5.0,
                'price' => 0.00,
                'reward_multiplier' => 5.0,
                'priority_level' => 100,
                'is_active' => true,
                'daily_task_limit' => 1000,
                'max_tasks_per_distribution' => 500,
                'distribution_priority' => 100,
            ]
        );

        // Create Super Admin membership
        $superAdminMembership = Membership::firstOrCreate(
            ['membership_name' => 'Super Admin'],
            [
                'description' => 'Super Administrator membership with unlimited access',
                'tasks_per_day' => 9999,
                'max_tasks' => 999999,
                'task_link' => 'https://superadmin.viralboast.com',
                'benefits' => 10.0,
                'price' => 0.00,
                'reward_multiplier' => 10.0,
                'priority_level' => 999,
                'is_active' => true,
                'daily_task_limit' => 9999,
                'max_tasks_per_distribution' => 9999,
                'distribution_priority' => 999,
            ]
        );

        // Create Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@viralboast.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@123!'),
                'referral_code' => 'ADMIN' . strtoupper(substr(md5('admin@viralboast.com'), 0, 5)),
                'email_verified_at' => now(),
                'is_active' => true,
                'phone' => '+1234567890',
                'phone_verified_at' => now(),
                'total_points' => 10000,
                'tasks_completed_today' => 0,
                'last_task_reset_date' => now()->toDateString(),
                'max_direct_referrals' => 1000,
                'direct_referrals_count' => 0,
                'indirect_referrals_count' => 0,
                'referral_bonus_earned' => 0.00,
                'direct_referral_bonus' => 10.00,
                'indirect_referral_bonus' => 5.00,
                'total_tasks' => 0,
                'is_admin' => true,
            ]
        );

        // Create Super Admin User
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@viralboast.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('SuperAdmin@2024!'),
                'referral_code' => 'SUPERADMIN' . strtoupper(substr(md5('superadmin@viralboast.com'), 0, 5)),
                'email_verified_at' => now(),
                'is_active' => true,
                'phone' => '+1234567891',
                'phone_verified_at' => now(),
                'total_points' => 100000,
                'tasks_completed_today' => 0,
                'last_task_reset_date' => now()->toDateString(),
                'max_direct_referrals' => 50000,
                'direct_referrals_count' => 0,
                'indirect_referrals_count' => 0,
                'referral_bonus_earned' => 0.00,
                'direct_referral_bonus' => 100.00,
                'indirect_referral_bonus' => 50.00,
                'total_tasks' => 0,
                'is_admin' => true,
            ]
        );

        // Assign Admin membership to admin user
        if (!$adminUser->memberships()->where('membership_id', $adminMembership->id)->exists()) {
            $adminUser->memberships()->attach($adminMembership->id, [
                'started_at' => now(),
                'expires_at' => null,
                'is_active' => true,
                'is_admin' => true,
                'daily_tasks_completed' => 0,
                'last_reset_date' => now()->toDateString(),
            ]);
        }

        // Assign Super Admin membership to super admin user
        if (!$superAdminUser->memberships()->where('membership_id', $superAdminMembership->id)->exists()) {
            $superAdminUser->memberships()->attach($superAdminMembership->id, [
                'started_at' => now(),
                'expires_at' => null,
                'is_active' => true,
                'is_admin' => true,
                'daily_tasks_completed' => 0,
                'last_reset_date' => now()->toDateString(),
            ]);
        }

        // Create Admin Account
        Account::firstOrCreate(
            ['user_uuid' => $adminUser->uuid],
            [
                'balance' => 10000.00,
                'total_bonus' => 0.00,
                'total_withdrawals' => 0.00,
                'tasks_income' => 0.00,
                'referral_income' => 0.00,
                'total_earned' => 10000.00,
                'is_active' => true,
                'last_activity_at' => now(),
            ]
        );

        // Create Super Admin Account
        Account::firstOrCreate(
            ['user_uuid' => $superAdminUser->uuid],
            [
                'balance' => 1000000.00,
                'total_bonus' => 0.00,
                'total_withdrawals' => 0.00,
                'tasks_income' => 0.00,
                'referral_income' => 0.00,
                'total_earned' => 1000000.00,
                'is_active' => true,
                'last_activity_at' => now(),
            ]
        );

        $this->command->info('Admin Users Created Successfully!');
        $this->command->info('');
        $this->command->info('Admin Login Details:');
        $this->command->info('==================');
        $this->command->info('Email: admin@viralboast.com');
        $this->command->info('Password: Admin@123!');
        $this->command->info('Account Balance: $10,000.00');
        $this->command->info('');
        $this->command->info('Super Admin Login Details:');
        $this->command->info('==========================');
        $this->command->info('Email: superadmin@viralboast.com');
        $this->command->info('Password: SuperAdmin@2024!');
        $this->command->info('Account Balance: $1,000,000.00');
        $this->command->info('');
        $this->command->info('Both users are verified and ready to use!');
    }
}
