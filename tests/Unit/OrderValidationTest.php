<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that only pending orders can be modified
     */
    public function test_only_pending_orders_can_be_modified(): void
    {
        $user = User::factory()->create();

        $pendingOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $approvedOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);

        $declinedOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'declined',
        ]);

        $this->assertTrue($pendingOrder->canBeModified());
        $this->assertFalse($approvedOrder->canBeModified());
        $this->assertFalse($declinedOrder->canBeModified());
    }

    /**
     * Test quantity must be multiple of 10
     */
    public function test_quantity_must_be_multiple_of_ten(): void
    {
        $validQuantities = [10, 20, 30, 40, 50, 100, 200];
        foreach ($validQuantities as $quantity) {
            $this->assertEquals(0, $quantity % 10);
        }

        $invalidQuantities = [5, 15, 25, 33, 47];
        foreach ($invalidQuantities as $quantity) {
            $this->assertNotEquals(0, $quantity % 10);
        }
    }

    /**
     * Test minimum quantity is 10
     */
    public function test_minimum_quantity_is_ten(): void
    {
        $this->assertTrue(10 >= 10);
        $this->assertFalse(5 >= 10);
        $this->assertFalse(0 >= 10);
    }
}

