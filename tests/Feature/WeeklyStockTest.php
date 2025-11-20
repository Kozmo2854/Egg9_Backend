<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeeklyStock;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyStockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test authenticated user can get current week's stock
     */
    public function test_can_get_current_week_stock(): void
    {
        $user = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/weekly-stock');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'weeklyStock' => [
                    'id', 'weekStart', 'weekEnd', 'availableEggs',
                    'pricePerDozen', 'isOrderingOpen', 'deliveryDate',
                    'deliveryTime', 'allOrdersDelivered',
                ],
            ]);
    }

    /**
     * Test returns 404 when no weekly stock exists
     */
    public function test_returns_404_when_no_weekly_stock(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/weekly-stock');

        $response->assertStatus(404);
    }

    /**
     * Test unauthenticated user cannot get weekly stock
     */
    public function test_unauthenticated_cannot_get_weekly_stock(): void
    {
        WeeklyStock::factory()->create();

        $response = $this->getJson('/api/weekly-stock');

        $response->assertStatus(401);
    }

    /**
     * Test can get available eggs
     */
    public function test_can_get_available_eggs(): void
    {
        $user = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/available-eggs');

        $response->assertStatus(200)
            ->assertJson([
                'availableEggs' => 1000,
            ]);
    }

    /**
     * Test available eggs calculation with existing orders
     */
    public function test_available_eggs_with_existing_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        Order::factory()->create([
            'user_id' => $otherUser->id,
            'quantity' => 100,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/available-eggs');

        $response->assertStatus(200)
            ->assertJson([
                'availableEggs' => 900, // 1000 - 100
            ]);
    }

    /**
     * Test available eggs calculation with user's own order
     */
    public function test_available_eggs_includes_users_own_order(): void
    {
        $user = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'quantity' => 50,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/available-eggs');

        $response->assertStatus(200)
            ->assertJson([
                'availableEggs' => 1050, // User's own 50 eggs order is added back to 1000
            ]);
    }
}

