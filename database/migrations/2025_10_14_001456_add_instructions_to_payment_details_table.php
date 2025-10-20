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
            $table->json('bitcoin_instructions')->nullable()->after('bitcoin_address');
            $table->json('ethereum_instructions')->nullable()->after('ethereum_address');
            $table->json('usdt_trc20_instructions')->nullable()->after('usdt_address_TRC20');
            $table->json('usdt_erc20_instructions')->nullable()->after('usdt_address_ERC20');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn([
                'bitcoin_instructions',
                'ethereum_instructions', 
                'usdt_trc20_instructions',
                'usdt_erc20_instructions'
            ]);
        });
    }
};
