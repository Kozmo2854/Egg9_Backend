# Backend Development Prompt for AI Agent

## Your Task

Build a complete Laravel backend API for the Egg9 mobile/web application. The frontend is already built in React Native + Expo and is waiting for your API.

## Project Context

**Egg9** is an egg selling platform connecting producers with customers. It has:
- Customer features: Browse stock, place one-time orders, manage weekly subscriptions
- Admin features: Manage weekly stock, delivery schedules, view all orders

**Key Business Rules:**
- Single product (eggs only)
- 1 dozen = 10 eggs (not 12!)
- All orders in multiples of 10
- Weekly stock system (admin sets availability each week)
- Subscriptions: 4-12 weeks, max 30 eggs/week, regular pricing (no discount)
- Multi-user with shared global stock

## What You Need to Build

### Technology Stack
- Laravel 10.x or 11.x
- MySQL or PostgreSQL
- Laravel Sanctum (token-based auth)
- RESTful JSON API with CORS support

### Database Tables

1. **users**: id, name, email, password, role (customer/admin), timestamps
2. **weekly_stocks**: id, week_start, week_end, available_eggs, price_per_dozen, is_ordering_open, delivery_date, delivery_time, all_orders_delivered, timestamps
3. **orders**: id, user_id, quantity, price_per_dozen, total, status (pending/approved/declined/completed), delivery_status (not_delivered/delivered), week_start, timestamps
4. **subscriptions**: id, user_id, quantity, frequency (weekly), period (4-12), weeks_remaining, status (active/paused/cancelled), next_delivery, timestamps

### Seed Data (for testing)
```
1. admin@egg9.com / password123 (Admin User, role: admin)
2. user1@egg9.com / password123 (John Smith, role: customer)  
3. user2@egg9.com / password123 (Jane Doe, role: customer)
```

### API Endpoints Required

**Authentication (Public):**
- POST /api/login - Authenticate user, return { user, token }
- POST /api/register - Register new customer
- POST /api/logout - Revoke token (protected)
- GET /api/user - Get current user (protected)

**Weekly Stock (Protected):**
- GET /api/weekly-stock - Get current week's stock info
- GET /api/available-eggs - Calculate available eggs for current user

**Orders (Protected, Customer):**
- POST /api/orders - Create order for current week
- PUT /api/orders/{id} - Update pending order
- GET /api/orders/current-week - Get user's order for this week
- DELETE /api/orders/{id} - Cancel pending order
- GET /api/orders - Get all user's orders

**Subscriptions (Protected, Customer):**
- POST /api/subscriptions - Create subscription (cancel old one if exists)
- GET /api/subscriptions/current - Get active subscription
- DELETE /api/subscriptions/{id} - Cancel subscription

**Admin Endpoints (Protected, Admin Role):**
- GET /api/admin/orders - All orders with user names
- GET /api/admin/subscriptions - All subscriptions with user names
- PUT /api/admin/weekly-stock - Update available eggs
- PUT /api/admin/delivery-info - Update delivery date/time
- POST /api/admin/orders/mark-delivered - Mark all orders as delivered
- PUT /api/admin/orders/{id}/approve - Approve order
- PUT /api/admin/orders/{id}/decline - Decline order

### Response Format

**Success:**
```json
{
  "user": { "id": 1, "name": "John", "email": "user@test.com", "role": "customer", "createdAt": "2024-01-01T00:00:00.000Z" },
  "token": "sanctum_token_here"
}
```

**Error:**
```json
{
  "message": "Error description",
  "errors": { "field": ["Error details"] }
}
```

### Key Implementation Notes

1. **Date Format**: ISO 8601 UTC (e.g., 2024-01-01T00:00:00.000Z)
2. **One Order Per Week**: User can only have one pending order per week (update if exists)
3. **One Active Subscription**: User can only have one active subscription (cancel old when creating new)
4. **Stock Calculation**: 
   - Available = WeeklyStock.available_eggs
   - Subtract all users' pending orders for current week
   - Subtract all active subscriptions
   - For current user: Add back their existing order quantity (so they can edit)
5. **Order Total**: (quantity / 10) * price_per_dozen
6. **Subscription Total**: (quantity / 10) * price_per_dozen * period (no discount)
7. **Weekly Automation**: REQUIRED - Laravel scheduled command to run every Monday at 00:01 to process subscriptions, create new weekly stock, and archive old week
8. **CORS**: Allow localhost:8081, localhost:19006, exp:// origins

### Validation Rules

- quantity: multiple of 10, min 10
- subscription quantity: multiple of 10, min 10, max 30
- subscription period: min 4, max 12 weeks
- Check sufficient stock before creating/updating orders
- Only order owner can update/cancel their order
- Only pending orders can be updated/cancelled

## Weekly Automation (CRITICAL REQUIREMENT)

**This is a core feature, not optional!**

Implement a Laravel scheduled command that runs **every Monday at 00:01**:

```bash
php artisan egg9:process-weekly-cycle
```

**What it must do:**
1. **Archive Previous Week**: Mark old weekly_stock as closed (is_ordering_open = false)
2. **Create New Week**: Create new weekly_stock record for current week (Monday-Monday)
   - available_eggs = 0 (admin will set)
   - price_per_dozen = 5.99 (or carry from previous)
   - delivery_date/time = null
   - all_orders_delivered = false
3. **Process Active Subscriptions**: For each active subscription:
   - Create a new order in orders table
   - Decrement weeks_remaining by 1
   - If weeks_remaining reaches 0, mark subscription as completed
   - Update next_delivery to next Monday
4. **Error Handling**: Log failures, notify admin if subscriptions can't be processed
5. **Testing**: Provide --force flag to test manually without waiting for Monday

**Deployment**: Add to server crontab:
```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

**Without this automation, subscriptions won't work!** See full details in BACKEND_REQUIREMENTS.md.

## Testing Requirements (MANDATORY)

**CRITICAL**: Write tests for everything!

### After EVERY code change:
1. Run unit tests: `php artisan test --filter Unit`
2. Run feature tests: `php artisan test --filter Feature`
3. Fix any failures immediately
4. Ensure all tests pass before moving forward

### What to Test:

**Unit Tests (minimum 20 tests):**
- Stock calculation logic
- Order validation (multiple of 10, min 10)
- Subscription validation (multiple of 10, max 30, period 4-12)
- Weekly automation logic
- Price calculations (1 dozen = 10 eggs)

**Feature Tests (minimum 50 tests for all 21 endpoints):**
- Authentication (login, register, logout)
- All order endpoints (create, update, cancel, list)
- All subscription endpoints
- All admin endpoints
- Authorization (users can only access their own data)
- Edge cases (stock exhaustion, invalid quantities, etc.)

**Test Coverage:** Minimum 80% overall, 95% for critical features

**Deliverables:**
- All tests passing: `php artisan test`
- Coverage report: `php artisan test --coverage`
- Postman collection with test scenarios
- TESTING.md documentation

**DO NOT commit code with failing tests!**

See BACKEND_REQUIREMENTS.md for complete testing specification.

---

## Deliverables

1. Complete Laravel project with migrations, seeders, controllers, models
2. **All endpoints implemented and fully tested**
3. **Unit tests (minimum 20) and Feature tests (minimum 50)**
4. **Test coverage report showing >80% coverage**
5. Postman collection or API documentation
6. Instructions for:
   - Database setup
   - Running migrations/seeders
   - **Running tests**
   - Starting the API server
   - API base URL for frontend integration
7. **TESTING.md** with testing guide
8. Brief integration guide for the React Native frontend

## Integration Instructions for Frontend Team

After completion, provide:
1. **API Base URL** (e.g., https://api.egg9.com/api)
2. **Token format** from Sanctum
3. Any special headers needed
4. Test credentials confirmation

The frontend will replace mock implementations in `services/api.ts` with real fetch() calls to your API.

## Full Documentation

For complete detailed specifications including:
- Full data models with all fields
- Complete request/response examples for every endpoint
- Business logic details
- Error handling requirements
- Testing requirements

See the `BACKEND_REQUIREMENTS.md` file in Egg9_Backend (70+ pages of complete specifications).

---

**Questions?** Ask for clarification on any business logic, endpoint behavior, or data models before starting. The frontend is fully built and matches these specifications exactly.

