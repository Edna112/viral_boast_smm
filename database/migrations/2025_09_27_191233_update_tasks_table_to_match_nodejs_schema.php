<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key constraints temporarily (MySQL specific)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Simply drop and recreate the tasks table
        Schema::dropIfExists('tasks');
        
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('description', 1000);
            $table->string('category', 50);
            $table->enum('task_type', ['social_media', 'website_visit', 'app_download', 'survey', 'other'])->default('other');
            $table->string('platform', 50);
            $table->string('instructions', 2000);
            $table->string('target_url');
            $table->decimal('benefit', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->enum('task_status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->integer('threshold_value');
            $table->integer('task_completion_count')->default(0);
            $table->integer('task_distribution_count')->default(0);
            $table->timestamps();
            
            // Add indexes for better query performance
            $table->index('title');
            $table->index('category');
            $table->index('task_type');
            $table->index('platform');
            $table->index('is_active');
            $table->index('task_status');
            $table->index('priority');
            $table->index('created_at');
        });
        
        // Re-enable foreign key constraints (MySQL specific)
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key constraints temporarily (MySQL specific)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        Schema::dropIfExists('tasks');
        
        // Recreate the original table structure
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('category_id')->constrained('task_categories')->onDelete('cascade');
            $table->enum('task_type', ['like', 'follow', 'subscribe', 'comment'])->default('like');
            $table->string('platform')->nullable();
            $table->text('instructions')->nullable();
            $table->string('target_url')->nullable();
            $table->json('requirements')->nullable();
            $table->integer('base_points')->default(10);
            $table->integer('estimated_duration_minutes')->default(5);
            $table->boolean('requires_photo')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('threshold_value')->default(0);
            $table->timestamps();
        });
        
        // Add the additional columns that were added later
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('task_completion_count')->default(0)->after('threshold_value');
            $table->string('category')->nullable()->after('task_completion_count');
            $table->decimal('reward', 10, 2)->default(10.00)->change();
            $table->integer('task_distribution_count')->default(0)->after('task_completion_count');
            $table->integer('distribution_threshold')->default(100)->after('task_distribution_count');
            $table->integer('completion_threshold')->default(100)->after('distribution_threshold');
        });
        
        // Re-enable foreign key constraints (MySQL specific)
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};