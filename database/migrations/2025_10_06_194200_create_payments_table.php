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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid');
            $table->string('picture_path')->nullable(); // For picture upload
            $table->decimal('amount', 10, 2); // Amount with 2 decimal places
            $table->text('description')->nullable(); // Optional description
            $table->boolean('is_approved')->default(false); // Approval status
            $table->decimal('conversion_amount', 18, 8)->nullable(); // For crypto conversion (8 decimal places for precision)
            $table->enum('conversion_currency', ['bitcoin', 'ethereum', 'btc'])->nullable(); // Crypto currency type
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');

            // Indexes for better performance
            $table->index(['user_uuid', 'is_approved']);
            $table->index(['amount', 'created_at']);
            $table->index(['conversion_currency', 'conversion_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
