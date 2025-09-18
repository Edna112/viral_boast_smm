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
        Schema::create('user_memberships', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid');
            $table->unsignedBigInteger('membership_id');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('daily_tasks_completed')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->timestamps();
            
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->unique(['user_uuid', 'membership_id']);
            $table->index(['user_uuid', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_memberships');
    }
};
