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
            if (!Schema::hasColumn('membership', 'daily_task_limit')) {
                $table->integer('daily_task_limit')->default(5)->after('is_active');
            }
            if (!Schema::hasColumn('membership', 'max_tasks_per_distribution')) {
                $table->integer('max_tasks_per_distribution')->default(3)->after('daily_task_limit');
            }
            if (!Schema::hasColumn('membership', 'distribution_priority')) {
                $table->integer('distribution_priority')->default(1)->after('max_tasks_per_distribution');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership', function (Blueprint $table) {
            $table->dropColumn(['daily_task_limit', 'max_tasks_per_distribution', 'distribution_priority']);
        });
    }
};