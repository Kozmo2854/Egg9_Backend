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
        Schema::table('weeks', function (Blueprint $table) {
            $table->boolean('is_low_season')->default(true)->after('all_orders_delivered');
            $table->boolean('subscriptions_processed')->default(false)->after('is_low_season');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weeks', function (Blueprint $table) {
            $table->dropColumn(['is_low_season', 'subscriptions_processed']);
        });
    }
};
