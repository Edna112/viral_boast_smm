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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid');
            $table->string('endpoint');
            $table->string('public_key');
            $table->string('auth_token');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->index(['user_uuid', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
