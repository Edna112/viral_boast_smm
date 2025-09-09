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
            $table->decimal('total_points', 12, 2)->default(0.00)->after('referred_by');
            $table->integer('tasks_completed')->default(0)->after('total_points');
            $table->integer('tasks_expired')->default(0)->after('tasks_completed');
            $table->timestamp('last_task_completed_at')->nullable()->after('tasks_expired');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_points', 'tasks_completed', 'tasks_expired', 'last_task_completed_at']);
        });
    }
};
