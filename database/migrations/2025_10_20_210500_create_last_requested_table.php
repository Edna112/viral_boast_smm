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
        Schema::create('last_requested', function (Blueprint $table) {
            // Primary key as user's UUID
            $table->uuid('user_uuid')->primary();

            // Last request timestamp (used for day gating)
            $table->timestamp('last_requested_at')->nullable();

            // Optional: track creation/update for auditing
            // Keeping timestamps off in model, but available here if needed elsewhere
            // $table->timestamps();

            // Optional: FK to users.uuid if your users table uses uuid
            // Uncomment if users.uuid exists and is the correct type
            // $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_requested');
    }
};



