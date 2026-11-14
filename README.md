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

### Docker Setup (recommended)

`docker-compose.yml` runs the API, MySQL and [Mailpit](https://mailpit.axllent.org/) (catches all outgoing mail).

```bash
# One-time: create your local .env (bind-mounted into the container)
cp .env.example .env

# Start all services (builds the backend image on first run; runs composer install + migrations)
docker compose up -d

# One-time: generate the app key
docker compose exec backend php artisan key:generate

# Optional: seed demo data
docker compose exec backend php artisan migrate:fresh --seed
```

| Service | URL / port |
|---------|------------|
| `backend` - Laravel (PHP 8.2 + Apache) | API: http://localhost:8000/api |
| `mysql` - MySQL 8.0 | `localhost:3307` (user `egg9_user` / `egg9_password`, db `egg9`) |
| `mailpit` - SMTP sink | UI + REST API: http://localhost:8025, SMTP `localhost:1025` |

Compose sets `MAIL_HOST=mailpit`, `QUEUE_CONNECTION=sync` and `SITE_URL=http://localhost:8081` for the backend, so every notification email lands in Mailpit. The project name is pinned to `egg9`, so the MySQL data volume (`egg9_mysql_data`) and the Mailpit volume (`egg9_mailpit_data`) are preserved across `down`/`up`. Never use `docker compose down -v` unless you want to wipe them.

This compose also creates the `egg9_network` network that `Egg9_Frontend/docker-compose.yml` joins, so start the backend first.

**Common Docker Commands:**
```bash
# Start/stop
docker compose up -d
docker compose down

# Run Artisan commands
docker compose exec backend php artisan migrate
# Tests: always force SQLite in-memory - the container env points at the real local MySQL
# and the tests use RefreshDatabase (would wipe it)
docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: backend php artisan test

# Database shell
docker compose exec mysql mysql -u egg9_user -pegg9_password egg9

# Reset database
docker compose exec backend php artisan migrate:fresh --seed
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

Copy `.env.example` to `.env`. With Docker nothing else is needed; for a host setup, configure the database credentials and point `MAIL_HOST`/`MAIL_PORT` at a reachable Mailpit (or set `MAIL_MAILER=log`).

### Production (Railway)

**Required environment variables:**

| Variable | Description | Example |
|----------|-------------|---------|
| `DB_CONNECTION` | Database driver | `mysql` |
| `FRONTEND_URL` | Frontend URL for CORS | `https://egg9frontend-production.up.railway.app` |
| `QUEUE_CONNECTION` | Queue driver (use `sync` for immediate processing) | `sync` |
| `CRON_SECRET` | Secret token for cron endpoint authentication | `your-secure-random-string` |
| `MAIL_MAILER` | Mail transport (`resend` in production) | `resend` |
| `RESEND_KEY` | Resend API key for email notifications | `re_...` |

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

### Email Delivery

- **Local and staging:** mail goes to [Mailpit](https://mailpit.axllent.org/), an SMTP sink with a web UI and REST API. Locally it runs in `docker-compose.yml` (SMTP `mailpit:1025`, UI http://localhost:8025). Nothing is delivered to real inboxes. See [TESTING.md](TESTING.md#mailpit) for the API.
- **Production:** mail is sent through [Resend](https://resend.com/) (`MAIL_MAILER=resend`, `RESEND_KEY`), configured in the Railway dashboard.

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
