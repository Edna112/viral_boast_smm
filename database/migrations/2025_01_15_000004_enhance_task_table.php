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
        Schema::table('task', function (Blueprint $table) {
            $table->integer('base_points')->default(10)->after('task_url');
            $table->text('description')->nullable()->after('base_points');
            $table->boolean('is_active')->default(true)->after('description');
            $table->integer('max_daily_assignments')->default(1000)->after('is_active');
            $table->dropColumn(['user_id', 'membership_id', 'status', 'duration']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task', function (Blueprint $table) {
            $table->integer('user_id')->nullable();
            $table->integer('membership_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('duration');
            $table->dropColumn(['base_points', 'description', 'is_active', 'max_daily_assignments']);
        });
    }
};
