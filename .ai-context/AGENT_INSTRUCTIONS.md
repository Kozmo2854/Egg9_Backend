# AI Agent Instructions - Egg9 Backend

## Project Context

Egg9 is a weekly egg delivery subscription platform with a React Native/Expo frontend. This Laravel backend provides the REST API.

## Critical Business Rules

1. **1 dozen = 10 eggs** (NOT 12!) - This is the most important rule
2. All quantities must be multiples of 10
3. Subscriptions: 2-4 weeks, max 30 eggs/week
4. One active subscription per user
5. One order per user per week (can be updated if pending)
6. Orders have three stages: Payment → Delivery → Pickup
7. Order status = `completed` only when paid + delivered + picked_up
8. Currency: Serbian Dinars (RSD), default 350 RSD per dozen

## Architecture Overview

### Service Layer Pattern

Business logic is extracted into service classes:
- `app/Services/OrderService.php` - Order operations
- `app/Services/SubscriptionService.php` - Subscription operations

**Controllers should:**
- Validate requests
- Call service methods
- Return formatted responses
- Handle authorization

**Services should:**
- Contain business logic
- Handle database transactions
- Throw ValidationException for errors
- Return models or formatted arrays

### Order Lifecycle States

```
Payment:  not_paid → payment_submitted (user) → paid (admin)
Delivery: pending → delivered (admin) → completed (when paid+delivered+picked_up)
Pickup:   not_picked_up → picked_up (user)
```

**Status Field:**
- `pending` - Initial state
- `delivered` - Admin marked as delivered
- `completed` - Paid + Delivered + Picked Up

### Stock Calculation

Available stock for a user:
```php
$available = $week->available_eggs 
    - (all pending orders for week)
    - (all active subscription quantities)
    + (user's existing order quantity for week)  // So they can edit
```

### Admin Protection (3-Layer)

1. **Backend Middleware** (`AdminMiddleware.php`)
2. **Frontend Route Config** (`href: null` for admin tabs)
3. **Component Redirect** (`useEffect` redirect if not admin)

## Database Models

### Order Model
- `checkAndUpdateCompletion()` - Auto-updates to `completed` when criteria met
- `canBeModified()` - Returns `$this->status === 'pending' && !$this->is_paid`

### Week Model
- `getCurrentWeek()` - Static method for current week
- `getAvailableEggsForUser($userId)` - Calculates user-specific available stock

### Subscription Model
- Relationship: `activeSubscription` on User model
- Creating new subscription auto-cancels old one

## Key API Behaviors

### Subscription Creation
- Validates stock availability
- Returns 409 error with `insufficient_stock_this_week` if:
  - `all_orders_delivered` is true for current week
  - Stock < required quantity
- Deletes pending orders from old subscription
- Creates order immediately if `start_next_week` is false
- Sets `weeks_remaining = period - 1` if starting this week (order already created)

### Order Management
- Only pending + unpaid orders can be modified
- Can't modify/cancel subscription orders directly
- User can mark payment submitted (any unpaid order)
- Admin confirms payment (changes `is_paid` to true)
- User confirms pickup (delivered orders only)

### Weekly Automation
Command: `php artisan egg9:process-weekly-cycle --force`

**Process:**
1. Move all previous weeks to past (set `week_end` to past date)
2. Create new week (Monday-Monday)
3. For each active subscription:
   - Create order for new week
   - Decrement `weeks_remaining`
   - If `weeks_remaining` reaches 0, mark subscription as cancelled
4. Call `checkAndUpdateCompletion()` on delivered orders

## Testing Requirements

**Before ANY commit:**
1. Run `php artisan test`
2. All tests must pass
3. Write tests for new features
4. Maintain >80% coverage

**Test Types:**
- Unit: Business logic, calculations, validations
- Feature: API endpoints, authentication, authorization

## Code Quality Standards

### PHPDoc Required
```php
/**
 * Create a new one-time order.
 *
 * @param \App\Models\User $user
 * @param int $quantity
 * @return array
 * @throws \Illuminate\Validation\ValidationException
 */
public function createOrder(User $user, int $quantity): array
```

### Error Responses
```php
throw ValidationException::withMessages([
    'field' => ['Human-readable error message']
]);
```

### API Response Format
```json
{
  "id": 1,
  "userId": 1,
  "quantity": 20,
  "total": 700,
  "status": "pending",
  "isPaid": false,
  "paymentSubmitted": false,
  "pickedUp": false,
  "weekStart": "2024-01-01T00:00:00.000Z"
}
```

**Use camelCase for JSON keys, snake_case for database fields.**

## Common Patterns

### Controller Method Template
```php
public function method(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([...]);
        
        $result = $this->service->doSomething($request->user(), $validated);
        
        return response()->json($result, 200);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'errors' => $e->errors()
        ], 422);
    }
}
```

### Service Method Template
```php
public function doSomething(User $user, array $data): array
{
    DB::beginTransaction();
    try {
        // Validation
        if ($someCondition) {
            throw ValidationException::withMessages([
                'field' => ['Error message']
            ]);
        }
        
        // Business logic
        $result = Model::create([...]);
        
        DB::commit();
        return $this->formatModel($result);
    } catch (Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

## Environment Variables

### Local Development
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
```

### Production (Railway)
```env
DB_CONNECTION=mysql  # MUST be set explicitly
FRONTEND_URL=https://frontend-url  # For CORS
# Railway auto-provides: MYSQL_HOST, MYSQL_PORT, etc.
```

## Frontend Integration

**Base URL:** `http://localhost:8000/api` (local), `https://backend-url/api` (production)

**Headers:**
```javascript
{
  'Authorization': 'Bearer {token}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}
```

**Frontend expects:**
- camelCase JSON keys
- ISO 8601 UTC dates
- Consistent error format
- Sanctum token from login/register

## Debugging Tips

### Database Issues
```bash
php artisan migrate:fresh --seed  # Reset everything
php artisan db:show              # Show DB info
```

### Test Failures
```bash
php artisan config:clear
php artisan cache:clear
php artisan test --filter TestName
```

### CORS Issues
- Check `config/cors.php` has correct origins
- Verify `FRONTEND_URL` environment variable
- Ensure `supports_credentials` is true

## File Organization

```
app/
├── Services/          # Business logic (preferred for new features)
├── Http/Controllers/  # Request handling only
├── Models/            # Eloquent models with relationships
└── Console/Commands/  # Artisan commands (cron jobs)
```

**Rule:** Keep controllers thin, services fat.

## Git Workflow

```bash
# File permissions are ignored (configured)
git config core.filemode false

# Before committing
php artisan test
php artisan pint  # Laravel code style fixer
```

## Migration Best Practices

- **Never** modify existing migrations after deployment
- Use descriptive names: `2024_01_01_000001_add_field_to_table.php`
- Always provide `down()` method
- Test migration rollback: `php artisan migrate:rollback`

## Subscription Logic Reference

```php
// Creating subscription
$weeks_remaining = $startNextWeek ? $period : $period - 1;

// Weekly automation
foreach ($subscriptions as $sub) {
    // Create order
    Order::create([...]);
    
    // Update subscription
    $sub->weeks_remaining--;
    if ($sub->weeks_remaining === 0) {
        $sub->status = 'cancelled';
    }
    $sub->save();
}
```

## Payment Flow Reference

```php
// User marks payment
$order->update(['payment_submitted' => true]);

// Admin confirms payment
$order->update(['is_paid' => true]);
$order->checkAndUpdateCompletion();  // Auto-complete if criteria met

// User confirms pickup
$order->update(['picked_up' => true]);
$order->checkAndUpdateCompletion();
```

## Important Notes

1. **Never skip tests** - They catch critical bugs
2. **Stock must be non-negative** - Validate before order creation
3. **Dates are UTC** - Frontend handles timezone conversion
4. **Admin endpoints** - Always use `AdminMiddleware`
5. **Transaction safety** - Use `DB::transaction()` for multi-step operations
6. **Dozen = 10 eggs** - This will never change, it's Serbian standard

## When Making Changes

1. Read existing code patterns
2. Write/update tests first
3. Implement feature in service layer
4. Add controller endpoint
5. Update routes with proper middleware
6. Run all tests
7. Update this documentation if needed
8. Commit with clear message

## References

- Main README: `README.md` - Project setup and API documentation
- Testing Guide: `TESTING.md` - Test suite overview
- Docker Setup: `DOCKER_SETUP.md` - Container configuration
- API Collection: `Egg9_API.postman_collection.json` - Import for testing

