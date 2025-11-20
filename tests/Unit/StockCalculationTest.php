<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WeeklyStock;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockCalculationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test available eggs calculation without any orders or subscriptions
     */
    public function test_available_eggs_without_orders_or_subscriptions(): void
    {
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $available = $weeklyStock->getAvailableEggsForUser(null);
        $this->assertEquals(1000, $available);
    }

    /**
     * Test available eggs calculation with pending orders
     */
    public function test_available_eggs_with_pending_orders(): void
    {
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create pending orders
        Order::factory()->create([
            'user_id' => $user1->id,
            'quantity' => 50,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        Order::factory()->create([
            'user_id' => $user2->id,
            'quantity' => 30,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        // Available should be 1000 - 50 - 30 = 920
        $available = $weeklyStock->getAvailableEggsForUser(null);
        $this->assertEquals(920, $available);
    }

    /**
     * Test available eggs calculation with active subscriptions
     */
    public function test_available_eggs_with_active_subscriptions(): void
    {
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create active subscriptions
        Subscription::factory()->create([
            'user_id' => $user1->id,
            'quantity' => 20,
            'status' => 'active',
        ]);

        Subscription::factory()->create([
            'user_id' => $user2->id,
            'quantity' => 30,
            'status' => 'active',
        ]);

        // Available should be 1000 - 20 - 30 = 950
        $available = $weeklyStock->getAvailableEggsForUser(null);
        $this->assertEquals(950, $available);
    }

    /**
     * Test available eggs for specific user includes their existing order
     */
    public function test_available_eggs_for_user_includes_their_order(): void
    {
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // User has an existing order
        Order::factory()->create([
            'user_id' => $user->id,
            'quantity' => 50,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        // Another user has an order
        Order::factory()->create([
            'user_id' => $otherUser->id,
            'quantity' => 30,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        // For the specific user, should be 1000 - 30 (other user's order) + 50 (their own) = 1020
        // Their own 50 eggs are added back
        $available = $weeklyStock->getAvailableEggsForUser($user->id);
        $this->assertEquals(1020, $available);

        // Without user ID, should be 1000 - 50 - 30 = 920
        $available = $weeklyStock->getAvailableEggsForUser(null);
        $this->assertEquals(920, $available);
    }

    /**
     * Test available eggs cannot be negative
     */
    public function test_available_eggs_cannot_be_negative(): void
    {
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 100,
        ]);

        $user = User::factory()->create();

        // Create orders that exceed available stock
        Order::factory()->create([
            'user_id' => $user->id,
            'quantity' => 150,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        // Should return 0, not negative
        $available = $weeklyStock->getAvailableEggsForUser(null);
        $this->assertEquals(0, $available);
        $this->assertGreaterThanOrEqual(0, $available);
    }

    /**
     * Test approved orders don't affect available stock
     */
    public function test_approved_orders_dont_affect_available_stock(): void
    {
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $user = User::factory()->create();

        // Approved order shouldn't affect available stock calculation
        Order::factory()->create([
            'user_id' => $user->id,
            'quantity' => 50,
            'status' => 'approved',
            'week_start' => $weeklyStock->week_start,
        ]);

        $available = $weeklyStock->getAvailableEggsForUser(null);
        $this->assertEquals(1000, $available);
    }
}

