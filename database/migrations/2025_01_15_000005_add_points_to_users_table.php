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
        Schema::table('users', function (Blueprint $table) {
            // Only add columns if they don't exist
            if (!Schema::hasColumn('users', 'total_points')) {
                $table->integer('total_points')->default(0)->after('referred_by');
            }
            if (!Schema::hasColumn('users', 'tasks_completed_today')) {
                $table->integer('tasks_completed_today')->default(0)->after('total_points');
            }
            if (!Schema::hasColumn('users', 'last_task_reset_date')) {
                $table->date('last_task_reset_date')->nullable()->after('tasks_completed_today');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns if they exist
            if (Schema::hasColumn('users', 'total_points')) {
                $table->dropColumn('total_points');
            }
            if (Schema::hasColumn('users', 'tasks_completed_today')) {
                $table->dropColumn('tasks_completed_today');
            }
            if (Schema::hasColumn('users', 'last_task_reset_date')) {
                $table->dropColumn('last_task_reset_date');
            }
        });
    }
};
