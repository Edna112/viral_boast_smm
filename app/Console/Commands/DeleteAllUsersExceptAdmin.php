<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAllUsersExceptAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:delete-all-except-admin {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all users from database except admin users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get admin users first
        $adminUsers = User::where('is_admin', true)->get();
        
        if ($adminUsers->isEmpty()) {
            $this->error('No admin users found! Cannot proceed without at least one admin user.');
            return 1;
        }

        // Count total users
        $totalUsers = User::count();
        $nonAdminUsers = User::where('is_admin', false)->count();
        
        $this->info("Found {$totalUsers} total users:");
        $this->info("- Admin users: {$adminUsers->count()}");
        $this->info("- Non-admin users: {$nonAdminUsers}");
        
        // Show admin users that will be preserved
        $this->info("\nAdmin users that will be preserved:");
        foreach ($adminUsers as $admin) {
            $this->line("- {$admin->name} ({$admin->email}) - UUID: {$admin->uuid}");
        }

        // Confirmation
        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete {$nonAdminUsers} non-admin users?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            DB::beginTransaction();
            
            // Delete all non-admin users
            $deletedCount = User::where('is_admin', false)->delete();
            
            DB::commit();
            
            $this->info("✅ Successfully deleted {$deletedCount} non-admin users.");
            $this->info("✅ Preserved {$adminUsers->count()} admin users.");
            
            // Show remaining users
            $remainingUsers = User::count();
            $this->info("📊 Remaining users in database: {$remainingUsers}");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error occurred: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
