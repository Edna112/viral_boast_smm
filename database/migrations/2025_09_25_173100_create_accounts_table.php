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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid');
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->decimal('total_bonus', 15, 2)->default(0.00);
            $table->decimal('total_withdrawals', 15, 2)->default(0.00);
            $table->decimal('tasks_income', 15, 2)->default(0.00);
            $table->decimal('referral_income', 15, 2)->default(0.00);
            $table->decimal('total_earned', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->unique('user_uuid');
            $table->index(['user_uuid', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};