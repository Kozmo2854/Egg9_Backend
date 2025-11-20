<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin user is correctly identified
     */
    public function test_admin_user_is_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($admin->isAdmin());
    }

    /**
     * Test customer user is not admin
     */
    public function test_customer_user_is_not_admin(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->assertFalse($customer->isAdmin());
    }

    /**
     * Test default role is customer
     */
    public function test_default_role_is_customer(): void
    {
        $user = User::factory()->create();
        $this->assertEquals('customer', $user->role);
        $this->assertFalse($user->isAdmin());
    }

    /**
     * Test user has orders relationship
     */
    public function test_user_has_orders_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->orders());
    }

    /**
     * Test user has subscriptions relationship
     */
    public function test_user_has_subscriptions_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->subscriptions());
    }
}

