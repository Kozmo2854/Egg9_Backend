<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WeeklyStock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test users as specified in requirements
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@egg9.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'John Smith',
            'email' => 'user1@egg9.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        User::create([
            'name' => 'Jane Doe',
            'email' => 'user2@egg9.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        // Create a current week's stock for testing
        $today = now();
        $weekStart = $today->copy()->startOfWeek(); // Monday
        $weekEnd = $weekStart->copy()->addWeek()->subDay(); // Next Sunday

        WeeklyStock::create([
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'available_eggs' => 1000,
            'price_per_dozen' => 5.99,
            'is_ordering_open' => true,
            'delivery_date' => $weekStart->copy()->addDays(5), // Saturday
            'delivery_time' => '10:00 AM - 2:00 PM',
            'all_orders_delivered' => false,
        ]);
    }
}
