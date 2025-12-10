# Egg9 Backend API

Laravel REST API for Egg9 - a weekly egg delivery subscription platform.

## Overview

Egg9 connects egg producers with customers through a weekly ordering system. Customers can place one-time orders or create multi-week subscriptions. Admins manage weekly stock, delivery schedules, payment settings, and order fulfillment.

**Key Features:**
- Token-based authentication (Laravel Sanctum)
- Weekly stock management with automated subscription processing
- Multi-user order system with shared stock
- Payment tracking with user submission and admin confirmation
- Three-stage order lifecycle: Payment → Delivery → Pickup
- Push & email notifications
- Admin user management and payment settings

**Critical Business Rule:** 1 dozen = 10 eggs (not 12!)

## Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL 8.0+
- Docker (optional)

### Local Setup

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Configure database in .env
php artisan migrate:fresh --seed
php artisan serve
```

API available at: `http://localhost:8000/api`

### Docker Setup

```bash
# Start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate:fresh --seed

# API available at: http://localhost:8000/api
# MySQL at: localhost:3307
```

**Docker Services:**
- `app` - Laravel (PHP 8.2 + Apache) on port 8000
- `db` - MySQL 8.0 on port 3307

**Common Docker Commands:**
```bash
# Start/stop
docker compose up -d
docker compose down

# Run Artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan test

# Database shell
docker compose exec db mysql -u egg9_user -pegg9_password egg9

# Reset database
docker compose exec app php artisan migrate:fresh --seed
```

## Technology Stack

- Laravel 11 with PHP 8.2
- MySQL 8.0 (SQLite for testing)
- Laravel Sanctum for API authentication
- Laravel Notifications (push + email)
- PHPUnit (87+ tests)

## Project Structure

```
app/
├── Channels/              # Custom notification channels (Expo Push)
├── Console/Commands/      # Weekly automation (cron)
├── Http/Controllers/      # API endpoints
├── Http/Middleware/       # Admin protection
├── Models/                # Eloquent models
├── Notifications/         # Notification classes
└── Services/              # Business logic layer

database/
├── migrations/            # Database schema
└── seeders/               # Test data

routes/api.php             # API route definitions
```

## API Endpoints

**Authentication:** `/api/login`, `/api/register`, `/api/logout`, `/api/user`

**Customer:** `/api/orders`, `/api/subscriptions`, `/api/weekly-stock`

**Admin:** `/api/admin/orders`, `/api/admin/subscriptions`, `/api/admin/users`, `/api/admin/settings`

For detailed endpoint documentation, see `routes/api.php` or import `Egg9_API.postman_collection.json`

## Testing

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage
```

See [TESTING.md](TESTING.md) for test suite details.

## Weekly Automation

Cron job runs every Monday at 00:01 to process subscriptions:

```bash
# Manual execution for testing
php artisan egg9:process-weekly-cycle --force
```

**Server crontab:** `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`

## Environment Configuration

### Local Development

Copy `.env.example` and configure database credentials.

### Production (Railway)

**Required environment variables:**

| Variable | Description | Example |
|----------|-------------|---------|
| `DB_CONNECTION` | Database driver | `mysql` |
| `FRONTEND_URL` | Frontend URL for CORS | `https://egg9frontend-production.up.railway.app` |
| `QUEUE_CONNECTION` | Queue driver (use `sync` for immediate processing) | `sync` |
| `CRON_SECRET` | Secret token for cron endpoint authentication | `your-secure-random-string` |
| `MAIL_PASSWORD` | Gmail App Password for email notifications | `your-16-char-app-password` |

**Notes:**
- Railway auto-provides MySQL credentials (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- `QUEUE_CONNECTION=sync` is **required** for notifications to work without a queue worker
- `CRON_SECRET` is required for the weekly cycle cron job endpoint

## Email & Push Notifications

Egg9 uses Laravel Notifications for both email and push notifications:

- Stock available notifications
- Order delivered notifications
- Delivery scheduled notifications
- Payment reminders (day after delivery)
- Pickup reminders (Monday after delivery)
- Subscription adjustment notifications

### Gmail SMTP Setup

1. Create a Gmail account for the app
2. Enable 2-Factor Authentication
3. Generate an App Password: https://myaccount.google.com/apppasswords
4. Configure environment variables:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=Egg9
```

### Push Notifications

Push notifications use Expo Push API. Mobile users automatically register their push tokens on login. Stale tokens are automatically cleaned up.

## Deployment

### Railway

Push to repository - Railway auto-deploys using `Dockerfile`.

**Post-deploy:**
```bash
railway run php artisan migrate
```

## Business Logic

See `../.ai-context/BUSINESS_LOGIC.md` for detailed business rules and order lifecycle documentation.

## Test Credentials

See `database/seeders/DatabaseSeeder.php` for seeded test accounts.

## Documentation

- **Main README**: This file
- **Testing Guide**: [TESTING.md](TESTING.md)
- **Business Logic**: `../.ai-context/BUSINESS_LOGIC.md`
- **AI Agent Guide**: `.ai-context/AGENT_INSTRUCTIONS.md`

---

**Last Updated**: December 10, 2024
