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
                'price' => 0.00,
                'benefit_amount_per_task' => 1.00,
                'is_active' => true,
            ]
        );

        // Create Admin membership
        $adminMembership = Membership::firstOrCreate(
            ['membership_name' => 'Admin'],
            [
                'description' => 'Administrator membership with full access',
                'tasks_per_day' => 1000,
                'max_tasks' => 100000,
                'price' => 0.00,
                'benefit_amount_per_task' => 5.00,
                'is_active' => true,
            ]
        );

        // Create Super Admin membership
        $superAdminMembership = Membership::firstOrCreate(
            ['membership_name' => 'Super Admin'],
            [
                'description' => 'Super Administrator membership with unlimited access',
                'tasks_per_day' => 9999,
                'max_tasks' => 999999,
                'price' => 0.00,
                'benefit_amount_per_task' => 10.00,
                'is_active' => true,
            ]
        );

        // Create Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@viralboast.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@123!'),
                'referral_code' => 'ADMIN' . strtoupper(substr(md5('admin@viralboast.com'), 0, 5)),
                'phone' => '+1234567890',
                'total_tasks' => 0,
                'total_completed_today' => 0,
                'profile_picture' => '',
                'membership_level' => $adminMembership->id,
                'role' => 'admin',
                'isActive' => true,
                'lastLogin' => now(),
                'email_verified_at' => now(), // Auto-verify admin email
                'phone_verified_at' => now(), // Auto-verify admin phone
            ]
        );

        // Create Super Admin User
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@viralboast.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('SuperAdmin@2024!'),
                'referral_code' => 'SUPERADMIN' . strtoupper(substr(md5('superadmin@viralboast.com'), 0, 5)),
                'phone' => '+1234567891',
                'total_tasks' => 0,
                'total_completed_today' => 0,
                'profile_picture' => '',
                'membership_level' => $superAdminMembership->id,
                'role' => 'admin',
                'isActive' => true,
                'lastLogin' => now(),
                'email_verified_at' => now(), // Auto-verify super admin email
                'phone_verified_at' => now(), // Auto-verify super admin phone
            ]
        );

        // Memberships are now assigned directly via membership_level field

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
