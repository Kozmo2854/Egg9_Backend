<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Order lifecycle:
     * - pending: Order placed, awaiting delivery
     * - delivered: Admin marked as delivered
     * - completed: Delivered + Paid + Picked up (all three conditions met)
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('week_id')->constrained('weeks')->onDelete('cascade');
            $table->integer('quantity'); // Number of eggs (multiples of 10)
            $table->decimal('total', 10, 2); // Total price
            $table->enum('status', ['pending', 'delivered', 'completed'])->default('pending');
            $table->boolean('is_paid')->default(false); // Confirmed by admin
            $table->boolean('payment_submitted')->default(false); // Marked as paid by user
            $table->boolean('picked_up')->default(false); // Confirmed pickup by user
            $table->timestamps();
            
            $table->index(['user_id', 'week_id']);
            $table->index(['subscription_id']);
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

