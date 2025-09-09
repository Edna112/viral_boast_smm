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
        Schema::create('vip_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Basic, VIP1, VIP2, VIP3, VIP4, VIP5, VIP6
            $table->string('slug')->unique(); // basic, vip1, vip2, vip3, vip4, vip5, vip6
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('reward_multiplier', 3, 1)->default(1.0); // 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0
            $table->integer('daily_task_limit')->default(1);
            $table->integer('max_tasks')->default(5);
            $table->integer('duration_days')->default(30); // Membership duration
            $table->json('benefits')->nullable(); // Additional benefits
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_memberships');
    }
};
