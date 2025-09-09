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
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->timestamp('assigned_at');
            $table->timestamp('expires_at');
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->string('completion_photo_url')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('base_points');
            $table->decimal('vip_multiplier', 3, 1);
            $table->integer('final_reward');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['expires_at']);
            $table->index(['assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
