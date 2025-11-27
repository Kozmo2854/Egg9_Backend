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
        // Rename the table
        Schema::rename('weekly_stocks', 'weeks');

        // Modify price_per_dozen to remove default (make it required)
        Schema::table('weeks', function (Blueprint $table) {
            $table->decimal('price_per_dozen', 8, 2)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore default value
        Schema::table('weeks', function (Blueprint $table) {
            $table->decimal('price_per_dozen', 8, 2)->default(5.99)->change();
        });

        // Rename back
        Schema::rename('weeks', 'weekly_stocks');
    }
};
