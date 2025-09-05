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
        Schema::create('membership', function (Blueprint $table) {
            $table->id();
            $table->string('userid');
            $table->string('membership_name')->unique();
            $table->text('description')->nullable();
            $table->float('price')->default(0.00);
            $table->float('benefits')->nullable();
            $table->float('tasks_per_day')->default(1);
            $table->float('max_tasks')->default(5);
            $table->string('task_link')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_');
    }
};
