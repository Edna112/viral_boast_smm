<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class NewAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating initial super admin...');

        // Create Super Admin
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'superadmin@viralboast.com'],
            [
                'name' => 'Super Administrator',
                'phone' => '+1234567890',
                'password' => Hash::make('SuperAdmin123!'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        if ($superAdmin->wasRecentlyCreated) {
            $this->command->info('Super Admin created successfully!');
            $this->command->info('Email: superadmin@viralboast.com');
            $this->command->info('Password: SuperAdmin123!');
        } else {
            $this->command->info('Super Admin already exists.');
        }

        // Create a regular admin for testing
        $regularAdmin = Admin::firstOrCreate(
            ['email' => 'admin@viralboast.com'],
            [
                'name' => 'Regular Administrator',
                'phone' => '+1234567891',
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        if ($regularAdmin->wasRecentlyCreated) {
            $this->command->info('Regular Admin created successfully!');
            $this->command->info('Email: admin@viralboast.com');
            $this->command->info('Password: Admin123!');
        } else {
            $this->command->info('Regular Admin already exists.');
        }
    }
}
