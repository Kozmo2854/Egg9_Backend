<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomElement([10, 20, 30]);
        $period = $this->faker->numberBetween(4, 12);

        return [
            'user_id' => User::factory(),
            'quantity' => $quantity,
            'frequency' => 'weekly',
            'period' => $period,
            'weeks_remaining' => $period,
            'status' => 'active',
            'next_delivery' => now()->addWeek()->startOfWeek(),
        ];
    }
}

