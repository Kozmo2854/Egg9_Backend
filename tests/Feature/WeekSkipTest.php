<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Week;
use App\Services\SeasonSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature/unit coverage for the "Skip this week" admin feature.
 *
 * Core invariant under test: skipping a week must NOT consume subscription weeks.
 * A subscription at weeks_remaining 1 stays at 1, at 2 stays at 2.
 */
class WeekSkipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stub the Expo push endpoint so notifications never hit the network
        Http::fake();
    }

    private function createWeek(array $overrides = []): Week
    {
        return Week::create(array_merge([
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->startOfWeek()->addDays(6),
            'available_eggs' => 200,
            'price_per_dozen' => 350.00,
            'is_ordering_open' => true,
            'all_orders_delivered' => false,
            'is_low_season' => false,
            'subscriptions_processed' => false,
            'is_skipped' => false,
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function service(): SeasonSubscriptionService
    {
        return app(SeasonSubscriptionService::class);
    }

    // ------------------------------------------------------------------
    // State (a): subscriptions NOT yet processed
    // ------------------------------------------------------------------

    public function test_skip_state_a_does_not_decrement_weeks_remaining(): void
    {
        $week = $this->createWeek(['subscriptions_processed' => false]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'weeks_remaining' => 2,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/week/skip');

        $response->assertStatus(200);
        $this->assertTrue($week->fresh()->is_skipped);

        // No decrement, no orders created
        $this->assertEquals(2, $sub->fresh()->weeks_remaining);
        $this->assertEquals('active', $sub->fresh()->status);
        $this->assertEquals(0, Order::where('week_id', $week->id)->count());
    }

    public function test_skipped_week_is_not_processed_when_stock_is_set(): void
    {
        // A skipped week must never process subscriptions even when stock is set later
        $week = $this->createWeek(['available_eggs' => 0, 'subscriptions_processed' => false, 'is_skipped' => true]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'weeks_remaining' => 2,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/week/current', ['available_eggs' => 200])
            ->assertStatus(200);

        // Gate held: still no processing, no decrement, no orders
        $this->assertFalse($week->fresh()->subscriptions_processed);
        $this->assertEquals(2, $sub->fresh()->weeks_remaining);
        $this->assertEquals(0, Order::where('week_id', $week->id)->count());
    }

    // ------------------------------------------------------------------
    // State (b): subscriptions ALREADY processed -> restore exactly
    // ------------------------------------------------------------------

    public function test_skip_state_b_restores_two_to_two(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'weeks_remaining' => 2,
            'status' => 'active',
        ]);

        // Realistic processing: decrements 2 -> 1 and creates the order
        $this->service()->processSubscriptionsForWeek($week);
        $this->assertEquals(1, $sub->fresh()->weeks_remaining);
        $this->assertEquals(1, Order::where('week_id', $week->id)->whereNotNull('subscription_id')->count());

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/week/skip')
            ->assertStatus(200);

        // Restored back to the original pre-week value, order removed
        $this->assertEquals(2, $sub->fresh()->weeks_remaining);
        $this->assertEquals('active', $sub->fresh()->status);
        $this->assertEquals(0, Order::where('week_id', $week->id)->count());
        $this->assertTrue($week->fresh()->is_skipped);
    }

    public function test_skip_state_b_restores_one_to_one_and_reverts_completed_to_active(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'weeks_remaining' => 1,
            'status' => 'active',
        ]);

        // Processing decrements 1 -> 0 and auto-completes the subscription
        $this->service()->processSubscriptionsForWeek($week);
        $this->assertEquals(0, $sub->fresh()->weeks_remaining);
        $this->assertEquals('completed', $sub->fresh()->status);

        $this->service()->skipWeek($week);

        // Restored to 1 and reverted to active
        $this->assertEquals(1, $sub->fresh()->weeks_remaining);
        $this->assertEquals('active', $sub->fresh()->status);
        $this->assertEquals(0, Order::where('week_id', $week->id)->count());
    }

    public function test_skip_does_not_restore_cancelled_subscription_but_removes_its_order(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'weeks_remaining' => 3,
            'status' => 'active',
        ]);

        $this->service()->processSubscriptionsForWeek($week);
        // User cancels after processing
        $sub->update(['status' => 'cancelled']);
        $cancelledRemaining = $sub->fresh()->weeks_remaining;

        $this->service()->skipWeek($week);

        // weeks_remaining untouched for cancelled subs, but the order is still removed
        $this->assertEquals($cancelledRemaining, $sub->fresh()->weeks_remaining);
        $this->assertEquals('cancelled', $sub->fresh()->status);
        $this->assertEquals(0, Order::where('week_id', $week->id)->count());
    }

    // ------------------------------------------------------------------
    // Block-if-paid
    // ------------------------------------------------------------------

    public function test_skip_is_blocked_with_409_when_a_paid_order_exists_and_mutates_nothing(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'weeks_remaining' => 2,
            'status' => 'active',
        ]);

        $this->service()->processSubscriptionsForWeek($week);
        $order = Order::where('week_id', $week->id)->first();
        $order->update(['is_paid' => true]);

        $weeksBefore = $sub->fresh()->weeks_remaining;

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/week/skip');

        $response->assertStatus(409);

        // Nothing mutated: order still present, week not skipped, weeks_remaining unchanged
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertFalse($week->fresh()->is_skipped);
        $this->assertEquals($weeksBefore, $sub->fresh()->weeks_remaining);
    }

    // ------------------------------------------------------------------
    // One-time orders
    // ------------------------------------------------------------------

    public function test_skip_hard_deletes_one_time_orders(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'week_id' => $week->id,
            'quantity' => 20,
            'total' => Order::calculateTotal(20, $week->price_per_dozen),
            'status' => 'pending',
            'is_paid' => false,
        ]);

        $this->service()->skipWeek($week);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertTrue($week->fresh()->is_skipped);
    }

    // ------------------------------------------------------------------
    // Un-skip
    // ------------------------------------------------------------------

    public function test_unskip_resets_flags_and_allows_reprocessing(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'weeks_remaining' => 2,
            'status' => 'active',
        ]);

        $this->service()->processSubscriptionsForWeek($week);
        $this->service()->skipWeek($week);
        $this->assertTrue($week->fresh()->is_skipped);

        // Un-skip clears is_skipped AND resets subscriptions_processed
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/week/unskip')
            ->assertStatus(200);

        $fresh = $week->fresh();
        $this->assertFalse($fresh->is_skipped);
        $this->assertFalse($fresh->subscriptions_processed);

        // Re-processing works cleanly: decrements once, single order
        $this->service()->processSubscriptionsForWeek($fresh);
        $this->assertEquals(1, $sub->fresh()->weeks_remaining);
        $this->assertEquals(1, Order::where('week_id', $week->id)->whereNotNull('subscription_id')->count());
    }

    // ------------------------------------------------------------------
    // Idempotency
    // ------------------------------------------------------------------

    public function test_skip_twice_is_a_noop(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'weeks_remaining' => 2,
            'status' => 'active',
        ]);

        $this->service()->processSubscriptionsForWeek($week);
        $this->service()->skipWeek($week);
        $afterFirst = $sub->fresh()->weeks_remaining;

        // Second skip must not restore again / not change anything
        $result = $this->service()->skipWeek($week->fresh());

        $this->assertTrue($result['already']);
        $this->assertEquals($afterFirst, $sub->fresh()->weeks_remaining);
        $this->assertEquals(0, Order::where('week_id', $week->id)->count());
    }

    public function test_skip_unskip_skip_leaves_weeks_remaining_at_original_with_no_orphan_orders(): void
    {
        $week = $this->createWeek(['available_eggs' => 200]);
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'quantity' => 20,
            'weeks_remaining' => 2,
            'status' => 'active',
        ]);

        $original = $sub->weeks_remaining; // 2

        // Process -> skip (state b) -> unskip -> skip (state a)
        $this->service()->processSubscriptionsForWeek($week);
        $this->service()->skipWeek($week->fresh());
        $this->service()->unskipWeek($week->fresh());
        $this->service()->skipWeek($week->fresh());

        $this->assertEquals($original, $sub->fresh()->weeks_remaining);
        $this->assertEquals('active', $sub->fresh()->status);
        $this->assertEquals(0, Order::where('week_id', $week->id)->count());
        $this->assertTrue($week->fresh()->is_skipped);
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    public function test_non_admin_cannot_skip_week(): void
    {
        $this->createWeek();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/admin/week/skip')
            ->assertStatus(403);
    }
}
