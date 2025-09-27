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
            if (!Schema::hasColumn('tasks', 'task_distribution_count')) {
                $table->integer('task_distribution_count')->default(0)->after('task_completion_count');
            }
            if (!Schema::hasColumn('tasks', 'distribution_threshold')) {
                $table->integer('distribution_threshold')->default(100)->after('task_distribution_count');
            }
            if (!Schema::hasColumn('tasks', 'completion_threshold')) {
                $table->integer('completion_threshold')->default(100)->after('distribution_threshold');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['task_distribution_count', 'distribution_threshold', 'completion_threshold']);
        });
    }
};