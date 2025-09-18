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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->uuid('referrer_uuid');
            $table->uuid('referred_user_uuid');
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('referrer_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->foreign('referred_user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->unique(['referrer_uuid', 'referred_user_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
