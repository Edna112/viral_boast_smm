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
        Schema::create('user_daily_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('user_uuid');
            $table->date('assignment_date'); // The date when tasks were assigned
            $table->integer('tasks_assigned_count')->default(0); // How many tasks assigned that day
            $table->json('assigned_task_ids')->nullable(); // Array of task IDs assigned that day
            $table->timestamps();
            
            // Indexes
            $table->index(['user_uuid', 'assignment_date']);
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_daily_task_assignments');
    }
};
