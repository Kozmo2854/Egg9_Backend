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
        Schema::table('app_settings', function (Blueprint $table) {
            $table->integer('max_subscription_eggs')->default(120)->after('default_price_per_dozen');
            $table->integer('max_per_subscription')->default(30)->after('max_subscription_eggs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['max_subscription_eggs', 'max_per_subscription']);
        });
    }
};
