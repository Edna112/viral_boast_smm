<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // First, drop the existing foreign key constraint if it exists
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'users' 
                AND CONSTRAINT_NAME LIKE '%membership_level%'
            ");
            
            // Drop foreign keys using raw SQL to avoid Laravel's naming issues
            foreach ($foreignKeys as $foreignKey) {
                DB::statement("ALTER TABLE `users` DROP FOREIGN KEY `{$foreignKey->CONSTRAINT_NAME}`");
            }
        });
        
        // Now change the column type and add the foreign key
        Schema::table('users', function (Blueprint $table) {
            // Change the column type from char(36) to unsignedBigInteger
            $table->unsignedBigInteger('membership_level')->nullable()->change();
            
            // Add the foreign key constraint
            $table->foreign('membership_level')->references('id')->on('membership')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['membership_level']);
            
            // Change the column type back to char(36)
            $table->char('membership_level', 36)->nullable()->change();
        });
    }
};