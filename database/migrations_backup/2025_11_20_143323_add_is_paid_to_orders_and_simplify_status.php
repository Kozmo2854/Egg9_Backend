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
        // Add is_paid column
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('status');
        });

        // Migrate existing data: if status is 'paid', set is_paid to true and status to 'pending'
        DB::statement("UPDATE orders SET is_paid = true, status = 'pending' WHERE status = 'paid'");

        // Now update the status enum to only have 'pending' and 'completed'
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the enum with 'paid' status
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'paid', 'completed') NOT NULL DEFAULT 'pending'");

        // Migrate data back: if is_paid is true and status is 'pending', set status to 'paid'
        DB::statement("UPDATE orders SET status = 'paid' WHERE is_paid = true AND status = 'pending'");

        // Drop the is_paid column
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_paid');
        });
    }
};
