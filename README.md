# Egg9 Backend API

Laravel backend API for the Egg9 mobile/web application - an egg selling platform connecting producers with customers.

## Key Features

- **Customer Features**: Browse stock, place orders, manage weekly subscriptions
- **Admin Features**: Manage weekly stock, delivery schedules, view all orders
- **Weekly Automation**: Automatic subscription processing every Monday at 00:01
- **Token-based Authentication**: Laravel Sanctum for secure API access

## Important Business Rules

- **1 dozen = 10 eggs** (not 12!)
- All orders in multiples of 10
- Subscriptions: 4-12 weeks, max 30 eggs/week
- Multi-user with shared global stock

## Technology Stack

- Laravel 11.x
- MySQL/PostgreSQL (SQLite for testing)
- Laravel Sanctum (API authentication)
- PHPUnit (testing)

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL or PostgreSQL
- PHP extensions: pdo, mbstring, openssl, json, tokenizer

### Setup Steps

1. **Clone the repository** (or you're already here!)

2. **Install dependencies**
```bash
composer install
```

3. **Configure environment**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database** in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=egg9
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. **Run migrations**
```bash
php artisan migrate
```

6. **Seed test data** (optional, for development):
```bash
php artisan db:seed
```

This creates:
- `admin@egg9.com` / `password123` (Admin)
- `user1@egg9.com` / `password123` (Customer: John Smith)
- `user2@egg9.com` / `password123` (Customer: Jane Doe)
- A current week's stock with 1000 eggs

7. **Start the server**
```bash
php artisan serve
```

The API will be available at: `http://localhost:8000/api`

## Running Tests

### Run all tests
```bash
php artisan test
```

### Run specific test suites
```bash
# Unit tests only
php artisan test --filter Unit

# Feature tests only
php artisan test --filter Feature
```

### Run with coverage
```bash
php artisan test --coverage
```

See [TESTING.md](TESTING.md) for detailed testing documentation.

## Weekly Automation

The system automatically processes subscriptions every Monday at 00:01 UTC.

### Manual Execution (for testing)
```bash
php artisan egg9:process-weekly-cycle --force
```

### Server Setup
Add to crontab:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## API Documentation

### Base URL
- Development: `http://localhost:8000/api`
- Production: `https://your-domain.com/api`

### Authentication
Most endpoints require authentication using Bearer tokens from Laravel Sanctum.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### API Endpoints

#### Authentication (Public)
- `POST /api/login` - Login user
- `POST /api/register` - Register new customer
- `POST /api/logout` - Logout (protected)
- `GET /api/user` - Get current user (protected)

#### Weekly Stock (Protected)
- `GET /api/weekly-stock` - Get current week's stock
- `GET /api/available-eggs` - Get available eggs for user

#### Orders (Protected, Customer)
- `GET /api/orders` - Get all user's orders
- `POST /api/orders` - Create order
- `GET /api/orders/current-week` - Get current week's order
- `PUT /api/orders/{id}` - Update pending order
- `DELETE /api/orders/{id}` - Cancel pending order

#### Subscriptions (Protected, Customer)
- `GET /api/subscriptions/current` - Get active subscription
- `POST /api/subscriptions` - Create subscription
- `DELETE /api/subscriptions/{id}` - Cancel subscription

#### Admin (Protected, Admin Role)
- `GET /api/admin/orders` - Get all orders with user info
- `GET /api/admin/subscriptions` - Get all subscriptions
- `PUT /api/admin/weekly-stock` - Update available eggs
- `PUT /api/admin/delivery-info` - Update delivery date/time
- `POST /api/admin/orders/mark-delivered` - Mark all as delivered
- `PUT /api/admin/orders/{id}/approve` - Approve order
- `PUT /api/admin/orders/{id}/decline` - Decline order

### Example Requests

#### Register
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123"
  }'
```

#### Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

#### Create Order
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 20
  }'
```

## CORS Configuration

The API is configured to accept requests from:
- `http://localhost:8081`
- `http://localhost:19000`
- `http://localhost:19006`
- Expo URLs (pattern: `exp://`)

Modify `config/cors.php` to add additional origins.

## Frontend Integration

The React Native frontend should:
1. Store the token from login/register responses
2. Include token in `Authorization: Bearer {token}` header
3. Use the API base URL from environment configuration
4. Handle 401 (unauthorized) responses by redirecting to login

Example API service:
```typescript
const API_URL = 'http://localhost:8000/api';

async function login(email: string, password: string) {
  const response = await fetch(`${API_URL}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  return await response.json();
}
```

## Project Structure

```
app/
├── Console/Commands/     # Weekly automation command
├── Http/
│   ├── Controllers/      # API controllers
│   └── Middleware/       # Admin middleware
└── Models/               # Eloquent models

database/
├── factories/            # Test data factories
├── migrations/           # Database schema
└── seeders/              # Test data seeders

routes/
├── api.php              # API routes
└── console.php          # Scheduled tasks

tests/
├── Feature/             # Feature tests (API endpoints)
└── Unit/                # Unit tests (business logic)
```

## Troubleshooting

### PHP Extensions Missing
If you get errors about missing PHP extensions:
```bash
# Ubuntu/Debian
sudo apt-get install php8.2-mbstring php8.2-mysql php8.2-xml

# macOS (Homebrew)
brew install php@8.2
```

### Database Connection Issues
- Verify MySQL/PostgreSQL is running
- Check database credentials in `.env`
- Ensure database exists: `CREATE DATABASE egg9;`

### Permission Issues
```bash
chmod -R 775 storage bootstrap/cache
```

## License

This project is proprietary software for the Egg9 application.

## Support

For questions or issues, contact the development team.
