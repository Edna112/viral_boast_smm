<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('membership', function (Blueprint $table) {
            // Remove columns that don't match the Node.js schema
            if (Schema::hasColumn('membership', 'userid')) {
                $table->dropColumn('userid');
            }
            if (Schema::hasColumn('membership', 'task_link')) {
                $table->dropColumn('task_link');
            }
            if (Schema::hasColumn('membership', 'benefits')) {
                $table->dropColumn('benefits');
            }
            if (Schema::hasColumn('membership', 'reward_multiplier')) {
                $table->dropColumn('reward_multiplier');
            }
            if (Schema::hasColumn('membership', 'priority_level')) {
                $table->dropColumn('priority_level');
            }
            if (Schema::hasColumn('membership', 'daily_task_limit')) {
                $table->dropColumn('daily_task_limit');
            }
            if (Schema::hasColumn('membership', 'max_tasks_per_distribution')) {
                $table->dropColumn('max_tasks_per_distribution');
            }
            if (Schema::hasColumn('membership', 'distribution_priority')) {
                $table->dropColumn('distribution_priority');
            }
        });

        // Recreate the table with the correct structure
        Schema::dropIfExists('membership');
        
        Schema::create('membership', function (Blueprint $table) {
            $table->id();
            $table->string('membership_name', 50)->unique();
            $table->string('description', 500);
            $table->integer('tasks_per_day')->default(0);
            $table->integer('max_tasks')->default(0);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('benefit_amount_per_task', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Add indexes for better query performance
            $table->index('membership_name');
            $table->index('is_active');
            $table->index('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership');
        
        // Recreate the original table structure
        Schema::create('membership', function (Blueprint $table) {
            $table->id();
            $table->string('userid');
            $table->string('membership_name')->unique();
            $table->text('description')->nullable();
            $table->float('price')->default(0.00);
            $table->float('benefits')->nullable();
            $table->float('tasks_per_day')->default(1);
            $table->float('max_tasks')->default(5);
            $table->string('task_link')->nullable();
            $table->timestamps();
        });
        
        // Add the additional columns that were added later
        Schema::table('membership', function (Blueprint $table) {
            $table->decimal('reward_multiplier', 3, 1)->default(1.0)->after('benefits');
            $table->integer('priority_level')->default(1)->after('reward_multiplier');
            $table->boolean('is_active')->default(true)->after('priority_level');
            $table->integer('daily_task_limit')->default(5)->after('is_active');
            $table->integer('max_tasks_per_distribution')->default(3)->after('daily_task_limit');
            $table->integer('distribution_priority')->default(1)->after('max_tasks_per_distribution');
        });
    }
};
