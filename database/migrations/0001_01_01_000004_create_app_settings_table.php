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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('default_price_per_dozen', 8, 2)->default(350.00);
            // Payment settings
            $table->string('bank_account_number', 30)->default('160-0000012345678-91');
            $table->string('recipient_name', 100)->default('EGG9 DOO');
            $table->string('payment_purpose', 150)->default('Placanje za jaja');
            $table->string('payment_code', 10)->default('289'); // SF code for goods/services
            $table->timestamps();
        });

        // Insert initial default settings (Serbian Dinars)
        DB::table('app_settings')->insert([
            'default_price_per_dozen' => 350.00,
            'bank_account_number' => '160-0000012345678-91',
            'recipient_name' => 'EGG9 DOO',
            'payment_purpose' => 'Placanje za jaja',
            'payment_code' => '289',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};

