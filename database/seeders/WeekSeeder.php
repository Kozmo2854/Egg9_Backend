<?php

namespace Database\Seeders;

use App\Models\Week;
use App\Models\AppSettings;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class WeekSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = AppSettings::first();
        $pricePerDozen = $settings ? $settings->default_price_per_dozen : 350.00;

        // Create current week
        $today = now();
        $weekStart = $today->copy()->startOfWeek(); // Monday
        $weekEnd = $weekStart->copy()->addWeek()->subDay(); // Next Sunday

        Week::create([
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'available_eggs' => 0,
            'price_per_dozen' => $pricePerDozen,
            'is_ordering_open' => false,
            'delivery_date' => null,
            'delivery_time' => null,
            'all_orders_delivered' => false,
        ]);

        $this->command->info("✓ Created current week");
        $this->command->info("  Start: {$weekStart->format('Y-m-d')} (Monday)");
        $this->command->info("  End: {$weekEnd->format('Y-m-d')} (Sunday)");
        $this->command->info("  Status: Ordering closed (admin must set stock)");
    }
}

