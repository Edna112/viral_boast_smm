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
        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('task_assignment_id')->nullable();
            $table->string('image_url'); // Cloudinary URL
            $table->string('public_id')->nullable(); // Cloudinary public ID for deletion
            $table->text('description')->nullable(); // Optional description
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable(); // Admin feedback
            $table->uuid('reviewed_by')->nullable(); // Admin who reviewed
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('task_assignment_id')->references('id')->on('task_assignments')->onDelete('set null');
            $table->foreign('reviewed_by')->references('uuid')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index(['user_uuid', 'status']);
            $table->index(['task_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_submissions');
    }
};