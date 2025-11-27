# Database Migration Consolidation - Nov 24, 2025

## What Was Done

### 1. ✅ Migration Backup
All original migrations have been backed up to:
```
database/migrations_backup/
```

### 2. ✅ Consolidated Migrations
Created clean, final-state migrations representing the current database schema:

1. **0001_01_01_000000_create_users_table.php**
   - Users with: id, name, email, phone_number, password, role
   - Password reset tokens
   - Sessions

2. **0001_01_01_000001_create_cache_table.php**
   - Cache and cache_locks tables

3. **0001_01_01_000002_create_jobs_table.php**
   - Jobs, job_batches, failed_jobs

4. **0001_01_01_000003_create_personal_access_tokens_table.php**
   - Sanctum authentication tokens

5. **0001_01_01_000004_create_app_settings_table.php**
   - App settings with default_price_per_dozen (350.00 RSD)

6. **0001_01_01_000005_create_weeks_table.php**
   - Weeks with: week_start, week_end, available_eggs, price_per_dozen, is_ordering_open, delivery_date, delivery_time, all_orders_delivered

7. **0001_01_01_000006_create_subscriptions_table.php**
   - Subscriptions with: user_id, quantity, frequency, period, weeks_remaining, status, next_delivery

8. **0001_01_01_000007_create_orders_table.php**
   - Orders with: user_id, subscription_id, week_id, quantity, total, status, is_paid
   - Foreign keys to users, subscriptions, and weeks tables

### 3. ✅ Updated Seeders

**DatabaseSeeder.php** - Creates 4 test users:
- **Admin**: admin@egg9.com / password123
- **User 1**: user1@egg9.com / password123 (Marko Marković)
- **User 2**: user2@egg9.com / password123 (Ana Jovanović)
- **User 3**: user3@egg9.com / password123 (Petar Petrović)

**WeekSeeder.php** - Creates the current week
- Week start: Current Monday
- Week end: Current Sunday
- Status: Ordering closed (admin must set stock)
- Price: 350 RSD per dozen

## Database Status

✅ **Fresh database created** with all tables and test data
✅ **Current week created** (Nov 24-30, 2025)
✅ **4 test users** ready to use
✅ **All migrations consolidated** into clean, final-state files

## Test User Credentials

| Role | Email | Password | Name |
|------|-------|----------|------|
| Admin | admin@egg9.com | password123 | Admin User |
| Customer | user1@egg9.com | password123 | Marko Marković |
| Customer | user2@egg9.com | password123 | Ana Jovanović |
| Customer | user3@egg9.com | password123 | Petar Petrović |

## Quick Login Buttons (Updated)

The login screen now has 4 quick login buttons:
- User 1
- User 2
- User 3
- Admin

All use password123 and match the seeded data.

## How to Create Additional Weeks

To create next week or future weeks:

```bash
# Run the WeekSeeder again to create next week
docker compose exec backend php artisan db:seed --class=WeekSeeder

# Note: The seeder currently creates the "current" week
# To test subscriptions properly, you'll need to:
# 1. Test Week 1 (current week - already created)
# 2. Use the weekly cycle command or manually create Week 2
# 3. Use the weekly cycle command or manually create Week 3
```

## Testing Workflow

### Week 1 (Current):
1. Login as admin
2. Set stock amount (e.g., 100 eggs)
3. Set delivery date and time
4. Login as users and create orders/subscriptions
5. Mark orders as delivered
6. Confirm payments

### Future Weeks:
Use the ProcessWeeklyCycle command:
```bash
docker compose exec backend php artisan egg9:process-weekly-cycle --force
```

This will:
- Archive the previous week
- Create a new week
- Process active subscriptions (create orders, decrement weeks_remaining)

## Database Reset Command

To start fresh at any time:
```bash
cd /home/j.zejnula/Projects/Egg9/Egg9_Backend
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan db:seed --class=WeekSeeder
```

This will:
1. Drop all tables
2. Re-run all migrations
3. Seed test users
4. Create current week

## Rollback Plan

If you need to revert to the old migrations:
```bash
# 1. Delete current migrations
rm database/migrations/*.php

# 2. Restore from backup
cp database/migrations_backup/* database/migrations/

# 3. Reset database
docker compose exec backend php artisan migrate:fresh --seed
```

## Notes

- All prices are in Serbian Dinars (RSD)
- Default price per dozen: 350 RSD
- Phone numbers use Serbian format (+381)
- Week starts on Monday, ends on Sunday
- Subscriptions are 2-4 weeks, 10-30 eggs per week (for 1 week, use one-time order)

