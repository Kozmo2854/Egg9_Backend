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
```

See [DOCKER_SETUP.md](DOCKER_SETUP.md) for detailed Docker configuration.

## Key Features

- Token-based authentication (Laravel Sanctum)
- Weekly stock management with automated subscription processing
- Multi-user order system with shared stock
- Payment tracking (user submission + admin confirmation)
- Three-stage order lifecycle: Payment → Delivery → Pickup
- Admin user management and payment settings

**Test Credentials**: See `database/seeders/DatabaseSeeder.php`

## Technology Stack

- Laravel 11 with PHP 8.2
- MySQL 8.0 (SQLite for testing)
- Laravel Sanctum for API authentication
- PHPUnit (87 tests, 100% passing)

## Project Structure

```
app/
├── Console/Commands/          # Weekly automation (cron)
├── Http/Controllers/          # API endpoints
├── Http/Middleware/           # Admin protection
├── Models/                    # Eloquent models
└── Services/                  # Business logic layer

database/
├── migrations/                # Database schema
└── seeders/                   # Test data

routes/api.php                 # API route definitions
```

## API Endpoints

**Authentication**: `/api/login`, `/api/register`, `/api/logout`, `/api/user`

**Customer**: `/api/orders`, `/api/subscriptions`, `/api/weekly-stock`

**Admin**: `/api/admin/orders`, `/api/admin/subscriptions`, `/api/admin/users`, `/api/admin/settings`

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

Automated cron job runs every Monday at 00:01:

```bash
# Manual execution for testing
php artisan egg9:process-weekly-cycle --force
```

**Server crontab**: `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`

## Environment Configuration

### Local Development
Copy `.env.example` and configure database credentials.

### Production (Railway)
Set environment variables:
- `DB_CONNECTION=mysql`
- `FRONTEND_URL=https://your-frontend-url` (for CORS)
- Railway auto-provides MySQL credentials

## Deployment

### Railway

Push to repository - Railway auto-deploys using `Dockerfile`.

**Post-deploy:**
```bash
railway run php artisan migrate:fresh --seed
```

## Business Logic

See `.ai-context/BUSINESS_LOGIC.md` for detailed business rules and order lifecycle documentation.

## Documentation

- **Main README**: This file
- **Docker Setup**: [DOCKER_SETUP.md](DOCKER_SETUP.md)
- **Testing Guide**: [TESTING.md](TESTING.md)
- **Business Logic**: `../.ai-context/BUSINESS_LOGIC.md`
- **AI Agent Guide**: `.ai-context/AGENT_INSTRUCTIONS.md`

## License

Proprietary - Egg9 Platform

## Support

For development questions or AI agent integration, see `.ai-context/` folder.

