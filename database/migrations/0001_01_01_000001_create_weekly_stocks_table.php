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
        Schema::create('weekly_stocks', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->integer('available_eggs')->default(0);
            $table->decimal('price_per_dozen', 8, 2)->default(5.99);
            $table->boolean('is_ordering_open')->default(true);
            $table->dateTime('delivery_date')->nullable();
            $table->string('delivery_time')->nullable();
            $table->boolean('all_orders_delivered')->default(false);
            $table->timestamps();
            
            $table->unique('week_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_stocks');
    }
};

