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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid')->nullable(); // Optional - complaints can be anonymous
            $table->enum('contact_type', ['email', 'phone'])->default('email');
            $table->string('contact', 255); // Email or phone number
            $table->enum('severity_level', ['low', 'medium', 'high'])->default('medium');
            $table->text('description');
            $table->text('admin_response')->nullable(); // Admin's response to the complaint
            $table->boolean('is_active')->default(true);
            $table->boolean('is_resolved')->default(false);
            $table->uuid('assigned_to')->nullable(); // Admin assigned to handle the complaint
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('assigned_to')->references('uuid')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index(['is_active', 'is_resolved']);
            $table->index(['severity_level', 'created_at']);
            $table->index(['contact_type', 'contact']);
            $table->index(['assigned_to', 'is_resolved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};