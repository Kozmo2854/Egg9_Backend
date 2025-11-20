<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderCalculationTest extends TestCase
{
    /**
     * Test order total calculation with basic quantities
     */
    public function test_calculate_total_with_basic_quantity(): void
    {
        // 10 eggs (1 dozen) at $5.99
        $total = Order::calculateTotal(10, 5.99);
        $this->assertEquals(5.99, $total);

        // 20 eggs (2 dozens) at $5.99
        $total = Order::calculateTotal(20, 5.99);
        $this->assertEquals(11.98, $total);

        // 30 eggs (3 dozens) at $5.99
        $total = Order::calculateTotal(30, 5.99);
        $this->assertEquals(17.97, $total);
    }

    /**
     * Test order total calculation with different prices
     */
    public function test_calculate_total_with_different_prices(): void
    {
        // 10 eggs at $6.50
        $total = Order::calculateTotal(10, 6.50);
        $this->assertEqualsWithDelta(6.50, $total, 0.01);

        // 50 eggs at $4.99
        $total = Order::calculateTotal(50, 4.99);
        $this->assertEqualsWithDelta(24.95, $total, 0.01);
    }

    /**
     * Test order total calculation with large quantities
     */
    public function test_calculate_total_with_large_quantities(): void
    {
        // 100 eggs (10 dozens) at $5.99
        $total = Order::calculateTotal(100, 5.99);
        $this->assertEqualsWithDelta(59.90, $total, 0.01);

        // 200 eggs (20 dozens) at $5.99
        $total = Order::calculateTotal(200, 5.99);
        $this->assertEqualsWithDelta(119.80, $total, 0.01);
    }

    /**
     * Test that 1 dozen equals 10 eggs (not 12!)
     */
    public function test_one_dozen_equals_ten_eggs(): void
    {
        // Critical business rule: 1 dozen = 10 eggs
        $total = Order::calculateTotal(10, 10.00);
        $this->assertEquals(10.00, $total); // 10 eggs / 10 * $10 = $10

        // Not 12 eggs!
        $total = Order::calculateTotal(12, 10.00);
        $this->assertEquals(12.00, $total); // 12 eggs / 10 * $10 = $12
    }
}

