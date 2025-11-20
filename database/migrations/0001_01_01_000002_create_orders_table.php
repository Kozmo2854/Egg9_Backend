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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price_per_dozen', 8, 2);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['pending', 'approved', 'declined', 'completed'])->default('pending');
            $table->enum('delivery_status', ['not_delivered', 'delivered'])->default('not_delivered');
            $table->date('week_start');
            $table->timestamps();
            
            $table->index(['user_id', 'week_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

