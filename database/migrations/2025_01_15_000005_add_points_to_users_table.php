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
            $table->integer('total_points')->default(0)->after('referred_by');
            $table->integer('tasks_completed_today')->default(0)->after('total_points');
            $table->date('last_task_reset_date')->nullable()->after('tasks_completed_today');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_points', 'tasks_completed_today', 'last_task_reset_date']);
        });
    }
};
