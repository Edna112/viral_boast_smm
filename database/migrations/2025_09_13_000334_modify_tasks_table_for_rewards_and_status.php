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
        Schema::table('tasks', function (Blueprint $table) {
            // Remove base_points column
            $table->dropColumn('base_points');
            
            // Add reward field
            $table->integer('reward')->default(10)->after('requirements');
            
            // Add task_status enum
            $table->enum('task_status', ['active', 'pause', 'completed', 'suspended'])->default('active')->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add back base_points column
            $table->integer('base_points')->default(10)->after('requirements');
            
            // Remove reward and task_status columns
            $table->dropColumn(['reward', 'task_status']);
        });
    }
};