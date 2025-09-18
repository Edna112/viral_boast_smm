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
            // Add task_completion_count as integer
            $table->integer('task_completion_count')->default(0)->after('threshold_value');
            
            // Add category as string
            $table->string('category')->nullable()->after('task_completion_count');
            
            // Change reward from integer to decimal
            $table->decimal('reward', 10, 2)->default(10.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn(['task_completion_count', 'category']);
            
            // Revert reward back to integer
            $table->integer('reward')->default(10)->change();
        });
    }
};
