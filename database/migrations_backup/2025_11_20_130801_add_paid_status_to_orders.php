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
        // Update the enum to include 'paid' status
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'paid', 'completed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert 'paid' orders back to 'pending' before removing the status
        DB::table('orders')
            ->where('status', 'paid')
            ->update(['status' => 'pending']);

        // Restore the previous enum without 'paid'
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
