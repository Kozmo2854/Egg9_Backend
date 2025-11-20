<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WeeklyStock>
 */
class WeeklyStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addWeek()->subDay();

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'available_eggs' => 1000,
            'price_per_dozen' => 5.99,
            'is_ordering_open' => true,
            'delivery_date' => $weekStart->copy()->addDays(5),
            'delivery_time' => '10:00 AM - 2:00 PM',
            'all_orders_delivered' => false,
        ];
    }
}

