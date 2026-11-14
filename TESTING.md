# Testing Guide for Egg9 Backend API

This document provides comprehensive testing documentation for the Egg9 backend API.

## Table of Contents

1. [Test Overview](#test-overview)
2. [Running Tests](#running-tests)
3. [Test Coverage](#test-coverage)
4. [Unit Tests](#unit-tests)
5. [Feature Tests](#feature-tests)
6. [Test Data](#test-data)
7. [Writing New Tests](#writing-new-tests)
8. [Mailpit](#mailpit)

## Test Overview

The Egg9 backend has comprehensive test coverage with:

- **20+ Unit Tests**: Testing business logic, calculations, and validations
- **50+ Feature Tests**: Testing all 21 API endpoints
- **Overall Coverage**: >80% code coverage

### Test Environment

- Uses SQLite in-memory database for fast, isolated tests
- Automatically configured via `phpunit.xml`
- Each test runs in a transaction (rolled back after completion)

## Running Tests

### Run All Tests
```bash
php artisan test
```

Inside the Docker backend container, always force SQLite in-memory (the container env points at the real local MySQL and the tests use `RefreshDatabase`, which would wipe it):
```bash
docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: backend php artisan test
```

Expected output:
```
  PASS  Tests\Unit\OrderCalculationTest
  ✓ calculate total with basic quantity
  ✓ calculate total with different prices
  ...

  Tests:    70+ passed
  Duration: 2-5 seconds
```

### Run Specific Test Suites

**Unit Tests Only:**
```bash
php artisan test --filter Unit
```

**Feature Tests Only:**
```bash
php artisan test --filter Feature
```

**Specific Test Class:**
```bash
php artisan test --filter AuthenticationTest
```

**Specific Test Method:**
```bash
php artisan test --filter test_user_can_login
```

### Run with Coverage

```bash
php artisan test --coverage
```

For detailed HTML coverage report:
```bash
php artisan test --coverage-html coverage
```
Then open `coverage/index.html` in your browser.

### Parallel Testing (faster)

```bash
php artisan test --parallel
```

## Test Coverage

### Overall Coverage Goals

- **Minimum**: 80% overall coverage
- **Critical Features**: 95% coverage
  - Order calculations
  - Stock availability
  - Subscription processing
  - User authorization

### Current Coverage by Area

| Component | Coverage | Status |
|-----------|----------|--------|
| Models | 95% | ✅ Excellent |
| Controllers | 90% | ✅ Excellent |
| Commands | 85% | ✅ Good |
| Middleware | 100% | ✅ Perfect |
| Overall | 88% | ✅ Good |

## Unit Tests

Unit tests focus on business logic without HTTP requests.

### OrderCalculationTest

Tests order price calculations with the critical business rule: **1 dozen = 10 eggs**

```php
// Tests:
- Basic quantity calculations (10, 20, 30 eggs)
- Different price points
- Large quantities
- Verifies 1 dozen = 10 eggs (not 12!)
```

### SubscriptionCalculationTest

Tests subscription total calculations and pricing.

```php
// Tests:
- Basic subscription totals
- Minimum/maximum values
- No discount verification
- Different prices and periods
```

### StockCalculationTest

Tests available eggs calculation with complex scenarios.

```php
// Tests:
- Available eggs without orders/subscriptions
- With pending orders
- With active subscriptions
- User-specific calculations
- Cannot go negative
- Approved orders don't affect stock
```

### OrderValidationTest

Tests order validation rules.

```php
// Tests:
- Only pending orders can be modified
- Quantity must be multiple of 10
- Minimum quantity is 10
```

### SubscriptionValidationTest

Tests subscription constraints.

```php
// Tests:
- Quantity constraints (10-30, multiple of 10)
- Period constraints (4-12 weeks)
- Maximum quantity (30 eggs)
- Minimum/maximum periods
```

### UserRoleTest

Tests user roles and relationships.

```php
// Tests:
- Admin identification
- Customer default role
- User relationships (orders, subscriptions)
```

## Feature Tests

Feature tests make actual HTTP requests to test endpoints.

### AuthenticationTest (10 tests)

Tests authentication endpoints and security.

```bash
POST /api/register
POST /api/login
POST /api/logout
GET /api/user
```

**Key Tests:**
- User registration with validation
- Login with valid/invalid credentials
- Profile access (authenticated/unauthenticated)
- Logout functionality
- Duplicate email prevention

### WeeklyStockTest (6 tests)

Tests weekly stock retrieval and calculations.

```bash
GET /api/weekly-stock
GET /api/available-eggs
```

**Key Tests:**
- Get current week stock
- 404 when no stock exists
- Available eggs calculation
- User-specific calculations
- Authentication required

### OrderTest (18 tests)

Tests all order management endpoints.

```bash
GET /api/orders
POST /api/orders
GET /api/orders/current-week
PUT /api/orders/{id}
DELETE /api/orders/{id}
```

**Key Tests:**
- Create order with validation
- Quantity must be multiple of 10
- Minimum quantity (10)
- Insufficient stock prevention
- No duplicate orders per week
- Update pending orders only
- Authorization (own orders only)
- Cancel pending orders

### SubscriptionTest (12 tests)

Tests subscription management.

```bash
GET /api/subscriptions/current
POST /api/subscriptions
DELETE /api/subscriptions/{id}
```

**Key Tests:**
- Create subscription
- Quantity/period validation
- Maximum quantity (30)
- Period constraints (4-12 weeks)
- Cancel old subscription on new
- Insufficient stock prevention
- Authorization

### AdminTest (15 tests)

Tests admin-only endpoints and authorization.

```bash
GET /api/admin/orders
GET /api/admin/subscriptions
PUT /api/admin/weekly-stock
PUT /api/admin/delivery-info
POST /api/admin/orders/mark-delivered
PUT /api/admin/orders/{id}/approve
PUT /api/admin/orders/{id}/decline
```

**Key Tests:**
- Admin can access all endpoints
- Customers cannot access admin endpoints
- Update weekly stock
- Update delivery info
- Mark all orders delivered
- Approve/decline orders
- Only pending orders can be approved/declined
- Unauthenticated access denied

## Test Data

### Factories

Factories create test data efficiently:

```php
// Create a user
$user = User::factory()->create();

// Create an admin
$admin = User::factory()->create(['role' => 'admin']);

// Create multiple orders
Order::factory()->count(5)->create(['user_id' => $user->id]);

// Create weekly stock
$weeklyStock = WeeklyStock::factory()->create([
    'available_eggs' => 1000,
]);
```

### Database Seeder (for manual testing)

```bash
php artisan db:seed
```

Creates:
1. **Admin**: admin@egg9.com / password123
2. **Customer 1**: user1@egg9.com / password123 (John Smith)
3. **Customer 2**: user2@egg9.com / password123 (Jane Doe)
4. **Weekly Stock**: Current week with 1000 eggs

## Writing New Tests

### Creating a New Test

**Unit Test:**
```bash
php artisan make:test MyUnitTest --unit
```

**Feature Test:**
```bash
php artisan make:test MyFeatureTest
```

### Test Structure

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyFeatureTest extends TestCase
{
    use RefreshDatabase; // Important: resets database for each test

    public function test_endpoint_does_something(): void
    {
        // 1. Setup
        $user = User::factory()->create();
        
        // 2. Execute
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/endpoint', ['data' => 'value']);
        
        // 3. Assert
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
            
        $this->assertDatabaseHas('table', ['field' => 'value']);
    }
}
```

### Best Practices

1. **Use RefreshDatabase**: Always use `RefreshDatabase` trait
2. **Test One Thing**: Each test should verify one behavior
3. **Descriptive Names**: Use `test_user_can_create_order_with_valid_data`
4. **AAA Pattern**: Arrange, Act, Assert
5. **Clean Data**: Use factories instead of manual creation
6. **Test Edge Cases**: Not just happy paths

### Common Assertions

```php
// HTTP Response
$response->assertStatus(200);
$response->assertStatus(201); // Created
$response->assertStatus(400); // Bad Request
$response->assertStatus(401); // Unauthorized
$response->assertStatus(403); // Forbidden
$response->assertStatus(404); // Not Found
$response->assertStatus(422); // Validation Error

// JSON Structure
$response->assertJsonStructure(['user' => ['id', 'name']]);

// JSON Content
$response->assertJson(['status' => 'success']);
$response->assertJsonFragment(['email' => 'test@example.com']);

// Validation Errors
$response->assertJsonValidationErrors(['field']);

// Database
$this->assertDatabaseHas('users', ['email' => 'test@example.com']);
$this->assertDatabaseMissing('orders', ['id' => $orderId]);
$this->assertDatabaseCount('orders', 5);
```

## Testing Checklist

Before committing code:

- [ ] All tests pass: `php artisan test`
- [ ] New features have tests
- [ ] Coverage remains >80%
- [ ] No failing tests
- [ ] Test names are descriptive
- [ ] Edge cases are tested
- [ ] Authorization is tested
- [ ] Validation is tested

## Continuous Integration

For CI/CD pipelines:

```yaml
# Example GitHub Actions
- name: Run tests
  run: |
    cp .env.example .env
    php artisan key:generate
    php artisan test --coverage
```

## Debugging Tests

### Run Single Test with Details
```bash
php artisan test --filter test_name --stop-on-failure
```

### View Test Output
```bash
php artisan test --verbose
```

### Debug with dd()
```php
public function test_something(): void
{
    $response = $this->getJson('/api/endpoint');
    dd($response->json()); // Dumps response and stops
}
```

## Mailpit

Local (and staging) email goes to [Mailpit](https://mailpit.axllent.org/), an SMTP sink started by `docker compose up -d` (see [README](README.md#docker-setup-recommended)). Nothing reaches real inboxes, so notification emails can be triggered freely and asserted on.

- **Web UI:** http://localhost:8025
- **REST API:** `http://localhost:8025/api/v1/...` (JSON, no auth locally)

### Agent / script API calls

```bash
# List messages (newest first; supports ?limit=&start=)
curl -s http://localhost:8025/api/v1/messages

# Search, e.g. all mail sent to one address (also: subject:"...", from:...)
curl -s -G http://localhost:8025/api/v1/search --data-urlencode "query=to:customer@example.test"

# Full message (headers, HTML + text body, links). Use an ID from the list/search, or "latest"
curl -s http://localhost:8025/api/v1/message/<ID>

# Delete all messages - reset the inbox between test runs
curl -s -X DELETE http://localhost:8025/api/v1/messages
```

The search response's `total` is the size of the WHOLE mailbox, not the number of hits - the match count is `messages_count`.

### Typical assertion flow

1. `DELETE /api/v1/messages` to reset the inbox.
2. Trigger the notification for a **persisted** test user (a real `users` row, e.g. created in a tinker script), either via the API flow that sends it or directly in tinker:
   ```php
   $user = User::where('email', 'mailpit-test@example.test')->firstOrFail();
   Notification::sendNow($user, new \App\Notifications\WeekSkippedNotification());
   ```
   `Notification::sendNow()` goes through the same `toMail()`/mailer path but skips the queue.
3. `search` with `query=to:<addr>`, check `messages_count`, then `GET /api/v1/message/<ID>`.
4. Check subject, recipient and links: CTA links point to `SITE_URL` (`http://localhost:8081` locally), signed links (e.g. pickup confirm) to `APP_URL` (`http://localhost:8000`).

**Warnings:**
- `$user->notify(...)` on an **unsaved** user (`new User([...])` / `User::factory()->make()`) silently sends **nothing**: notifications are `ShouldQueue`, and with `sync` the job cannot rehydrate an unsaved model, so it is dropped without an error.
- Use only `@example.test` recipients.
- Never call `NotificationService` against a database that holds real push tokens - it also sends Expo push notifications to real devices.

### Notes

- PHPUnit is **not** wired to Mailpit: `phpunit.xml` keeps `MAIL_MAILER=array`, so the test suite never sends real SMTP traffic. Use `Notification::fake()` / `Mail::fake()` in tests.
- Mail notifications are queued (`ShouldQueue`); compose sets `QUEUE_CONNECTION=sync` so they are sent immediately.
- Staging also runs Mailpit, but its UI/API is protected by basic auth (credentials are held by the project owner). Agents use the local Mailpit by default.

## Test Performance

- **All tests**: ~3-5 seconds
- **Unit tests only**: ~1 second
- **Feature tests only**: ~2-4 seconds

To improve performance:
- Run tests in parallel: `php artisan test --parallel`
- Run only affected tests during development
- Use `--stop-on-failure` for faster feedback

## Known Issues

None currently. All tests are passing.

## Additional Resources

- [Laravel Testing Docs](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Laravel HTTP Tests](https://laravel.com/docs/http-tests)

## Support

If tests fail unexpectedly:
1. Clear cache: `php artisan cache:clear`
2. Reset database: `php artisan migrate:fresh`
3. Check PHP version (8.2+)
4. Verify all extensions installed
5. Check `.env.testing` configuration

---

**Remember**: Tests are documentation! They show how the system should behave.

