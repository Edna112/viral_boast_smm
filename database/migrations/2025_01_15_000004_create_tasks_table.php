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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('category_id')->constrained('task_categories')->onDelete('cascade');
            $table->enum('task_type', ['like', 'follow', 'subscribe', 'comment'])->default('like');
            $table->string('platform')->nullable(); // instagram, facebook, twitter, youtube, etc.
            $table->text('instructions')->nullable();
            $table->string('target_url')->nullable();
            $table->json('requirements')->nullable(); // Specific requirements for the task
            $table->integer('base_points')->default(10);
            $table->integer('estimated_duration_minutes')->default(5);
            $table->boolean('requires_photo')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);  
            $table->integer('threshold_value')->default(0); // Minimum threshold for task completion
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
