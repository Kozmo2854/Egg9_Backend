<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@egg9.com',
            'phone_number' => '+381 60 123 4567',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // Create Test Customer 1
        User::create([
            'name' => 'Marko Marković',
            'email' => 'user1@egg9.com',
            'phone_number' => '+381 62 111 2222',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        // Create Test Customer 2
        User::create([
            'name' => 'Ana Jovanović',
            'email' => 'user2@egg9.com',
            'phone_number' => '+381 63 333 4444',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        // Create Test Customer 3
        User::create([
            'name' => 'Petar Petrović',
            'email' => 'user3@egg9.com',
            'phone_number' => '+381 64 555 6666',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        $this->command->info('Users created successfully!');
        $this->command->info('Admin: admin@egg9.com / password123');
        $this->command->info('User 1: user1@egg9.com / password123');
        $this->command->info('User 2: user2@egg9.com / password123');
        $this->command->info('User 3: user3@egg9.com / password123');
    }
}
