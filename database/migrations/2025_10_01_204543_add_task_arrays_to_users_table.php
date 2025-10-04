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
        Schema::table('users', function (Blueprint $table) {
            $table->json('assigned_tasks')->nullable()->after('profile_image');
            $table->json('completed_tasks')->nullable()->after('assigned_tasks');
            $table->json('inprogress_tasks')->nullable()->after('completed_tasks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['assigned_tasks', 'completed_tasks', 'inprogress_tasks']);
        });
    }
};
