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
        // Step 1: Add week_id column (nullable temporarily) if it doesn't exist
        if (!Schema::hasColumn('orders', 'week_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('week_id')->nullable()->after('user_id');
            });
        }

        // Step 2: Populate week_id based on week_start (only if week_start column exists)
        if (Schema::hasColumn('orders', 'week_start')) {
            DB::statement('
                UPDATE orders 
                SET week_id = (
                    SELECT id 
                    FROM weeks 
                    WHERE weeks.week_start = orders.week_start
                    LIMIT 1
                )
            ');
        }

        // Step 3: Make week_id required and add foreign key
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('week_id')->nullable(false)->change();
            
            // Check if foreign key doesn't already exist
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'orders' 
                AND COLUMN_NAME = 'week_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            if (empty($foreignKeys)) {
                $table->foreign('week_id')->references('id')->on('weeks')->onDelete('cascade');
            }
            
            // Add index if it doesn't exist
            $indexes = DB::select("SHOW INDEX FROM orders WHERE Column_name = 'week_id'");
            if (empty($indexes)) {
                $table->index('week_id');
            }
        });

        // Step 4: Drop redundant columns if they exist
        $columnsToDrop = [];
        if (Schema::hasColumn('orders', 'price_per_dozen')) {
            $columnsToDrop[] = 'price_per_dozen';
        }
        if (Schema::hasColumn('orders', 'delivery_status')) {
            $columnsToDrop[] = 'delivery_status';
        }
        if (Schema::hasColumn('orders', 'week_start')) {
            $columnsToDrop[] = 'week_start';
        }
        
        if (!empty($columnsToDrop)) {
            Schema::table('orders', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        // Step 5: Add new composite index if it doesn't exist
        $compositeIndex = DB::select("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'orders' 
            AND INDEX_NAME = 'orders_user_id_week_id_index'
        ");
        
        if (empty($compositeIndex)) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['user_id', 'week_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore old columns
        Schema::table('orders', function (Blueprint $table) {
            $table->date('week_start')->nullable();
            $table->decimal('price_per_dozen', 8, 2)->nullable();
            $table->enum('delivery_status', ['not_delivered', 'delivered'])->default('not_delivered');
        });

        // Populate week_start from week_id
        DB::statement('
            UPDATE orders 
            SET week_start = (
                SELECT week_start 
                FROM weeks 
                WHERE weeks.id = orders.week_id
                LIMIT 1
            )
        ');

        // Drop week_id
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'week_id']);
            $table->dropForeign(['week_id']);
            $table->dropColumn('week_id');
        });

        // Restore old index
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'week_start']);
        });
    }
};
