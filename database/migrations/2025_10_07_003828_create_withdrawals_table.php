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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid');
            $table->decimal('withdrawal_amount', 10, 2); // Withdrawal amount with 2 decimal places
            $table->string('platform')->nullable(); // Platform used for withdrawal
            $table->string('picture_path')->nullable(); // For withdrawal proof image
            $table->boolean('is_completed')->default(false); // Completion status
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');

            // Indexes for better performance
            $table->index(['user_uuid', 'is_completed']);
            $table->index(['withdrawal_amount', 'created_at']);
            $table->index(['platform', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
