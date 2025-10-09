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
        Schema::table('payments', function (Blueprint $table) {
            // Modify the enum to include USD
            $table->enum('conversion_currency', ['USD', 'bitcoin', 'ethereum', 'btc'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('conversion_currency', ['bitcoin', 'ethereum', 'btc'])->nullable()->change();
        });
    }
};
