<?php

namespace Tests\Unit;

use App\Models\Subscription;
use PHPUnit\Framework\TestCase;

class SubscriptionCalculationTest extends TestCase
{
    /**
     * Test subscription total calculation with basic values
     */
    public function test_calculate_subscription_total_basic(): void
    {
        // 10 eggs for 4 weeks at $5.99 per dozen
        $total = Subscription::calculateTotal(10, 5.99, 4);
        $this->assertEquals(23.96, $total);

        // 20 eggs for 8 weeks at $5.99 per dozen
        $total = Subscription::calculateTotal(20, 5.99, 8);
        $this->assertEquals(95.84, $total);
    }

    /**
     * Test subscription total with maximum values
     */
    public function test_calculate_subscription_total_maximum(): void
    {
        // Maximum: 30 eggs for 12 weeks at $5.99
        $total = Subscription::calculateTotal(30, 5.99, 12);
        $this->assertEquals(215.64, $total);
    }

    /**
     * Test subscription total with minimum values
     */
    public function test_calculate_subscription_total_minimum(): void
    {
        // Minimum: 10 eggs for 4 weeks at $5.99
        $total = Subscription::calculateTotal(10, 5.99, 4);
        $this->assertEquals(23.96, $total);
    }

    /**
     * Test that subscription has no discount (regular pricing)
     */
    public function test_subscription_has_no_discount(): void
    {
        // Subscription total should equal (weekly_order_total * period)
        $quantity = 20;
        $price = 5.99;
        $period = 6;

        $weeklyTotal = ($quantity / 10) * $price; // $11.98
        $subscriptionTotal = Subscription::calculateTotal($quantity, $price, $period);

        $this->assertEquals($weeklyTotal * $period, $subscriptionTotal);
    }

    /**
     * Test subscription calculation with different prices
     */
    public function test_calculate_subscription_with_different_prices(): void
    {
        // 10 eggs for 5 weeks at $7.50
        $total = Subscription::calculateTotal(10, 7.50, 5);
        $this->assertEqualsWithDelta(37.50, $total, 0.01);

        // 30 eggs for 10 weeks at $4.99
        $total = Subscription::calculateTotal(30, 4.99, 10);
        $this->assertEqualsWithDelta(149.70, $total, 0.01);
    }
}

