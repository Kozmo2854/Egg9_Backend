<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomElement([10, 20, 30, 40, 50]);
        $pricePerDozen = 5.99;
        $total = ($quantity / 10) * $pricePerDozen;

        return [
            'user_id' => User::factory(),
            'quantity' => $quantity,
            'price_per_dozen' => $pricePerDozen,
            'total' => $total,
            'status' => 'pending',
            'delivery_status' => 'not_delivered',
            'week_start' => now()->startOfWeek(),
        ];
    }
}

