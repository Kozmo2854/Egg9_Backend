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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('quantity'); // eggs per week (multiples of 10, max 30)
            $table->enum('frequency', ['weekly'])->default('weekly');
            $table->integer('period'); // Total duration in weeks (2-4)
            $table->integer('weeks_remaining'); // Weeks left in subscription
            $table->enum('status', ['active', 'paused', 'cancelled', 'completed'])->default('active');
            $table->date('next_delivery')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

