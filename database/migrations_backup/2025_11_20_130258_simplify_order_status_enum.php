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
        // Convert existing 'approved' orders to 'pending'
        DB::table('orders')
            ->where('status', 'approved')
            ->update(['status' => 'pending']);

        // Delete 'declined' orders
        DB::table('orders')
            ->where('status', 'declined')
            ->delete();

        // Update the enum to only have 'pending' and 'completed'
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the original enum with all statuses
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'approved', 'declined', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
