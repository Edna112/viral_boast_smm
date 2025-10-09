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
        Schema::table('payment_details', function (Blueprint $table) {
            // Drop the old usdt_address column
            $table->dropColumn('usdt_address');
            
            // Add the new USDT address columns
            $table->string('usdt_address_TRC20')->nullable();
            $table->string('usdt_address_ERC20')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            // Drop the new columns
            $table->dropColumn(['usdt_address_TRC20', 'usdt_address_ERC20']);
            
            // Add back the old column
            $table->string('usdt_address')->nullable();
        });
    }
};
