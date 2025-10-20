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
        Schema::table('user_daily_task_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_id')->nullable()->after('assigned_task_ids');
            $table->integer('membership_tasks_per_day')->nullable()->after('membership_id');
            
            // Add foreign key constraint
            $table->foreign('membership_id')->references('id')->on('membership')->onDelete('set null');
            
            // Add index for better performance
            $table->index(['user_uuid', 'membership_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_daily_task_assignments', function (Blueprint $table) {
            $table->dropForeign(['membership_id']);
            $table->dropIndex(['user_uuid', 'membership_id']);
            $table->dropColumn(['membership_id', 'membership_tasks_per_day']);
        });
    }
};
