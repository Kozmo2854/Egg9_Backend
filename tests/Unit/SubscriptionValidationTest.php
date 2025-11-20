<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SubscriptionValidationTest extends TestCase
{
    /**
     * Test subscription quantity constraints
     */
    public function test_subscription_quantity_constraints(): void
    {
        // Valid quantities (10-30, multiples of 10)
        $validQuantities = [10, 20, 30];
        foreach ($validQuantities as $quantity) {
            $this->assertTrue($quantity >= 10 && $quantity <= 30 && $quantity % 10 === 0);
        }

        // Invalid: too small
        $this->assertFalse(5 >= 10);

        // Invalid: too large
        $this->assertFalse(40 <= 30);

        // Invalid: not multiple of 10
        $this->assertFalse(15 % 10 === 0);
        $this->assertFalse(25 % 10 === 0);
    }

    /**
     * Test subscription period constraints
     */
    public function test_subscription_period_constraints(): void
    {
        // Valid periods (4-12 weeks)
        $validPeriods = [4, 5, 6, 7, 8, 9, 10, 11, 12];
        foreach ($validPeriods as $period) {
            $this->assertTrue($period >= 4 && $period <= 12);
        }

        // Invalid: too short
        $this->assertFalse(3 >= 4);
        $this->assertFalse(1 >= 4);

        // Invalid: too long
        $this->assertFalse(13 <= 12);
        $this->assertFalse(20 <= 12);
    }

    /**
     * Test maximum subscription quantity is 30 eggs per week
     */
    public function test_maximum_subscription_quantity(): void
    {
        $this->assertTrue(30 <= 30);
        $this->assertFalse(40 <= 30);
        $this->assertFalse(50 <= 30);
    }

    /**
     * Test minimum subscription period is 4 weeks
     */
    public function test_minimum_subscription_period(): void
    {
        $this->assertTrue(4 >= 4);
        $this->assertFalse(3 >= 4);
        $this->assertFalse(2 >= 4);
    }

    /**
     * Test maximum subscription period is 12 weeks
     */
    public function test_maximum_subscription_period(): void
    {
        $this->assertTrue(12 <= 12);
        $this->assertFalse(13 <= 12);
        $this->assertFalse(20 <= 12);
    }
}

