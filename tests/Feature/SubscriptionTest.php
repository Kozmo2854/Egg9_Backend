<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeeklyStock;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can create a subscription
     */
    public function test_user_can_create_subscription(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions', [
                'quantity' => 20,
                'period' => 8,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'subscription' => [
                    'id', 'userId', 'quantity', 'frequency',
                    'period', 'weeksRemaining', 'status', 'nextDelivery',
                ],
            ])
            ->assertJson([
                'subscription' => [
                    'quantity' => 20,
                    'period' => 8,
                    'weeksRemaining' => 8,
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'quantity' => 20,
            'period' => 8,
        ]);
    }

    /**
     * Test subscription quantity must be multiple of 10
     */
    public function test_subscription_quantity_must_be_multiple_of_10(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions', [
                'quantity' => 25,
                'period' => 8,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /**
     * Test subscription quantity maximum is 30
     */
    public function test_subscription_quantity_maximum_is_30(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions', [
                'quantity' => 40,
                'period' => 8,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /**
     * Test subscription period minimum is 4 weeks
     */
    public function test_subscription_period_minimum_is_4(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions', [
                'quantity' => 20,
                'period' => 3,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    /**
     * Test subscription period maximum is 12 weeks
     */
    public function test_subscription_period_maximum_is_12(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions', [
                'quantity' => 20,
                'period' => 15,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    /**
     * Test new subscription cancels old active subscription
     */
    public function test_new_subscription_cancels_old_subscription(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create([
            'available_eggs' => 1000,
        ]);

        $oldSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions', [
                'quantity' => 20,
                'period' => 8,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $oldSubscription->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test cannot create subscription with insufficient stock
     */
    public function test_cannot_create_subscription_with_insufficient_stock(): void
    {
        $user = User::factory()->create();
        WeeklyStock::factory()->create([
            'available_eggs' => 10,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions', [
                'quantity' => 20,
                'period' => 8,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'Insufficient stock available for subscription',
            ]);
    }

    /**
     * Test user can get their active subscription
     */
    public function test_user_can_get_active_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscriptions/current');

        $response->assertStatus(200)
            ->assertJson([
                'subscription' => [
                    'id' => $subscription->id,
                ],
            ]);
    }

    /**
     * Test returns null when no active subscription
     */
    public function test_returns_null_when_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscriptions/current');

        $response->assertStatus(200)
            ->assertJson([
                'subscription' => null,
            ]);
    }

    /**
     * Test user can cancel their subscription
     */
    public function test_user_can_cancel_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Subscription cancelled successfully',
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test user cannot cancel another user's subscription
     */
    public function test_user_cannot_cancel_another_users_subscription(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot create subscription
     */
    public function test_unauthenticated_cannot_create_subscription(): void
    {
        WeeklyStock::factory()->create();

        $response = $this->postJson('/api/subscriptions', [
            'quantity' => 20,
            'period' => 8,
        ]);

        $response->assertStatus(401);
    }
}

