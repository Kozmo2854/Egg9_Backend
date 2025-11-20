<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeeklyStock;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can create an order
     */
    public function test_user_can_create_order(): void
    {
        $user = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
            'price_per_dozen' => 5.99,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'quantity' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'order' => [
                    'id', 'userId', 'quantity', 'pricePerDozen',
                    'total', 'status', 'deliveryStatus', 'weekStart',
                ],
            ])
            ->assertJson([
                'order' => [
                    'quantity' => 20,
                    'pricePerDozen' => 5.99,
                    'total' => 11.98, // (20/10) * 5.99
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'quantity' => 20,
        ]);
    }

    /**
     * Test order quantity must be multiple of 10
     */
    public function test_order_quantity_must_be_multiple_of_10(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'quantity' => 15,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /**
     * Test order quantity must be at least 10
     */
    public function test_order_quantity_minimum_is_10(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'quantity' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /**
     * Test cannot create order with insufficient stock
     */
    public function test_cannot_create_order_with_insufficient_stock(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create([
            'available_eggs' => 50,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'quantity' => 100,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Insufficient stock available',
            ]);
    }

    /**
     * Test cannot create duplicate order for same week
     */
    public function test_cannot_create_duplicate_order_for_same_week(): void
    {
        $user = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'week_start' => $weeklyStock->week_start,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'quantity' => 20,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'You already have a pending order for this week. Please update it instead.',
            ]);
    }

    /**
     * Test user can get all their orders
     */
    public function test_user_can_get_all_orders(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'orders' => [
                    '*' => ['id', 'userId', 'quantity', 'total', 'status'],
                ],
            ])
            ->assertJsonCount(3, 'orders');
    }

    /**
     * Test user can get current week order
     */
    public function test_user_can_get_current_week_order(): void
    {
        $user = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'week_start' => $weeklyStock->week_start,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders/current-week');

        $response->assertStatus(200)
            ->assertJson([
                'order' => [
                    'id' => $order->id,
                ],
            ]);
    }

    /**
     * Test returns null when no current week order
     */
    public function test_returns_null_when_no_current_week_order(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders/current-week');

        $response->assertStatus(200)
            ->assertJson([
                'order' => null,
            ]);
    }

    /**
     * Test user can update their pending order
     */
    public function test_user_can_update_pending_order(): void
    {
        $user = User::factory()->create();
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
            'price_per_dozen' => 5.99,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'status' => 'pending',
            'week_start' => $weeklyStock->week_start,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/orders/{$order->id}", [
                'quantity' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'order' => [
                    'quantity' => 30,
                    'total' => 17.97, // (30/10) * 5.99
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'quantity' => 30,
        ]);
    }

    /**
     * Test user cannot update another user's order
     */
    public function test_user_cannot_update_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        WeeklyStock::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/orders/{$order->id}", [
                'quantity' => 30,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test cannot update approved order
     */
    public function test_cannot_update_approved_order(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/orders/{$order->id}", [
                'quantity' => 30,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'Only pending orders can be updated',
            ]);
    }

    /**
     * Test user can cancel their pending order
     */
    public function test_user_can_cancel_pending_order(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Order cancelled successfully',
            ]);

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
        ]);
    }

    /**
     * Test user cannot cancel another user's order
     */
    public function test_user_cannot_cancel_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot create order
     */
    public function test_unauthenticated_cannot_create_order(): void
    {
        WeeklyStock::factory()->create();

        $response = $this->postJson('/api/orders', [
            'quantity' => 20,
        ]);

        $response->assertStatus(401);
    }
}

