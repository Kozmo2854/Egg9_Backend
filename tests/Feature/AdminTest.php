<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeeklyStock;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can get all orders
     */
    public function test_admin_can_get_all_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Order::factory()->create(['user_id' => $user1->id]);
        Order::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'orders' => [
                    '*' => ['id', 'userId', 'userName', 'userEmail', 'quantity', 'total', 'status'],
                ],
            ])
            ->assertJsonCount(2, 'orders');
    }

    /**
     * Test customer cannot access admin orders endpoint
     */
    public function test_customer_cannot_get_all_orders(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/orders');

        $response->assertStatus(403);
    }

    /**
     * Test admin can get all subscriptions
     */
    public function test_admin_can_get_all_subscriptions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Subscription::factory()->create(['user_id' => $user1->id]);
        Subscription::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/subscriptions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscriptions' => [
                    '*' => ['id', 'userId', 'userName', 'userEmail', 'quantity', 'period', 'status'],
                ],
            ])
            ->assertJsonCount(2, 'subscriptions');
    }

    /**
     * Test customer cannot access admin subscriptions endpoint
     */
    public function test_customer_cannot_get_all_subscriptions(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/subscriptions');

        $response->assertStatus(403);
    }

    /**
     * Test admin can update weekly stock
     */
    public function test_admin_can_update_weekly_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $weeklyStock = WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/weekly-stock', [
                'availableEggs' => 1500,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'weeklyStock' => [
                    'availableEggs' => 1500,
                ],
            ]);

        $this->assertDatabaseHas('weekly_stocks', [
            'id' => $weeklyStock->id,
            'available_eggs' => 1500,
        ]);
    }

    /**
     * Test customer cannot update weekly stock
     */
    public function test_customer_cannot_update_weekly_stock(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        WeeklyStock::factory()->create();

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson('/api/admin/weekly-stock', [
                'availableEggs' => 1500,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test admin can update delivery info
     */
    public function test_admin_can_update_delivery_info(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $weeklyStock = WeeklyStock::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/delivery-info', [
                'deliveryDate' => '2024-12-15',
                'deliveryTime' => '2:00 PM - 5:00 PM',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'weeklyStock' => ['deliveryDate', 'deliveryTime'],
            ]);

        $this->assertDatabaseHas('weekly_stocks', [
            'id' => $weeklyStock->id,
            'delivery_time' => '2:00 PM - 5:00 PM',
        ]);
    }

    /**
     * Test customer cannot update delivery info
     */
    public function test_customer_cannot_update_delivery_info(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        WeeklyStock::factory()->create();

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson('/api/admin/delivery-info', [
                'deliveryDate' => '2024-12-15',
                'deliveryTime' => '2:00 PM - 5:00 PM',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test admin can mark all orders as delivered
     */
    public function test_admin_can_mark_all_orders_delivered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $weeklyStock = WeeklyStock::factory()->create();
        $user = User::factory()->create();

        Order::factory()->count(3)->create([
            'user_id' => $user->id,
            'week_start' => $weeklyStock->week_start,
            'delivery_status' => 'not_delivered',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/orders/mark-delivered');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'updatedCount' => 3,
            ]);

        $this->assertDatabaseHas('weekly_stocks', [
            'id' => $weeklyStock->id,
            'all_orders_delivered' => true,
        ]);
    }

    /**
     * Test admin can approve an order
     */
    public function test_admin_can_approve_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'order' => [
                    'id' => $order->id,
                    'status' => 'approved',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Test admin cannot approve already approved order
     */
    public function test_admin_cannot_approve_already_approved_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/approve");

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'Only pending orders can be approved',
            ]);
    }

    /**
     * Test admin can decline an order
     */
    public function test_admin_can_decline_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/decline");

        $response->assertStatus(200)
            ->assertJson([
                'order' => [
                    'id' => $order->id,
                    'status' => 'declined',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'declined',
        ]);
    }

    /**
     * Test customer cannot approve orders
     */
    public function test_customer_cannot_approve_orders(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test customer cannot decline orders
     */
    public function test_customer_cannot_decline_orders(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/decline");

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access admin endpoints
     */
    public function test_unauthenticated_cannot_access_admin_endpoints(): void
    {
        $response = $this->getJson('/api/admin/orders');
        $response->assertStatus(401);

        $response = $this->getJson('/api/admin/subscriptions');
        $response->assertStatus(401);

        $response = $this->putJson('/api/admin/weekly-stock', ['availableEggs' => 1000]);
        $response->assertStatus(401);
    }
}

