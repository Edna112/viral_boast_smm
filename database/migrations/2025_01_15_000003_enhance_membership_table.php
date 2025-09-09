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
        Schema::table('membership', function (Blueprint $table) {
            $table->decimal('reward_multiplier', 3, 1)->default(1.0)->after('benefits');
            $table->integer('priority_level')->default(1)->after('reward_multiplier');
            $table->boolean('is_active')->default(true)->after('priority_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership', function (Blueprint $table) {
            $table->dropColumn(['reward_multiplier', 'priority_level', 'is_active']);
        });
    }
};
