# Docker Setup Guide for Egg9 Backend

This guide will help you run the Egg9 backend API using Docker, solving all PHP extension and database connection issues.

## Prerequisites

- Docker installed ([Get Docker](https://docs.docker.com/get-docker/))
- Docker Compose installed (usually comes with Docker Desktop)

## Quick Start (Automated)

The easiest way to get started:

```bash
cd /home/j.zejnula/Projects/Egg9_Backend
./docker-setup.sh
```

This script will:
1. Stop any existing containers
2. Build the Docker images
3. Start MySQL and Laravel containers
4. Install Composer dependencies
5. Run database migrations and seeders
6. Set proper permissions

**Total time: ~2-3 minutes**

## Manual Setup

If you prefer to run commands manually:

### 1. Configure Environment

Copy the Docker environment configuration:

```bash
cp .env.example .env
```

Then edit `.env` with these values:

```env
APP_NAME=Egg9
APP_ENV=local
APP_KEY=base64:v4wtW7NFs98n9SMW/qgfCX0g9GcIwNBFfd/tJbW13fc=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=egg9
DB_USERNAME=egg9_user
DB_PASSWORD=egg9_password

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1
BCRYPT_ROUNDS=4
```

### 2. Build and Start Containers

```bash
docker compose build
docker compose up -d
```

### 3. Install Dependencies

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

### 4. Run Migrations

```bash
docker compose exec app php artisan migrate:fresh --seed
```

## What's Included

### Services

1. **app** - Laravel application (PHP 8.2 + Apache)
   - Port: 8000
   - All required PHP extensions installed
   - Auto-configured with Apache

2. **db** - MySQL 8.0 database
   - Port: 3307 (external), 3306 (internal)
   - Database: `egg9`
   - User: `egg9_user`
   - Password: `egg9_password`

### Docker Configuration Files

- **Dockerfile** - Laravel app container definition
- **docker compose.yml** - Multi-container orchestration
- **docker-setup.sh** - Automated setup script
- **.dockerignore** - Files to exclude from Docker build

## Access Points

Once containers are running:

- **API**: http://localhost:8000/api
- **Health Check**: http://localhost:8000/up
- **MySQL**: localhost:3307 (use any MySQL client)

## Common Commands

### Container Management

```bash
# Start containers
docker compose up -d

# Stop containers
docker compose down

# Restart containers
docker compose restart

# View container status
docker compose ps

# View logs (all services)
docker compose logs -f

# View logs (specific service)
docker compose logs -f app
docker compose logs -f db
```

### Application Commands

```bash
# Run Artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan route:list

# Run tests
docker compose exec app php artisan test

# Enter app container shell
docker compose exec app bash

# Run Composer commands
docker compose exec app composer install
docker compose exec app composer update
```

### Database Commands

```bash
# Access MySQL shell
docker compose exec db mysql -u egg9_user -pegg9_password egg9

# Dump database
docker compose exec db mysqldump -u egg9_user -pegg9_password egg9 > backup.sql

# Restore database
docker compose exec -T db mysql -u egg9_user -pegg9_password egg9 < backup.sql

# Reset database
docker compose exec app php artisan migrate:fresh --seed
```

## Testing the API

### 1. Test Authentication

```bash
# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@egg9.com","password":"password123"}'
```

**Expected response:**
```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@egg9.com",
    "role": "admin",
    "createdAt": "2024-..."
  },
  "token": "1|..."
}
```

### 2. Test Weekly Stock

```bash
# Get current week stock
curl http://localhost:8000/api/weekly-stock \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Test Order Creation

```bash
# Create order
curl -X POST http://localhost:8000/api/orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"quantity":20}'
```

### 4. Run All Tests

```bash
docker compose exec app php artisan test
```

Expected: **70+ tests passing** ✅

## Test Credentials

After running migrations with `--seed`:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@egg9.com | password123 |
| Customer | user1@egg9.com | password123 |
| Customer | user2@egg9.com | password123 |

## Troubleshooting

### Containers won't start

```bash
# Check logs
docker compose logs

# Rebuild from scratch
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

### Database connection errors

```bash
# Wait for MySQL to be fully ready
docker compose exec db mysqladmin ping -h localhost

# Check database exists
docker compose exec db mysql -u root -proot_password -e "SHOW DATABASES;"
```

### Permission errors

```bash
# Fix Laravel storage permissions
docker compose exec app chown -R www-data:www-data /var/www/html/storage
docker compose exec app chmod -R 775 /var/www/html/storage
docker compose exec app chmod -R 775 /var/www/html/bootstrap/cache
```

### Port already in use

If port 8000 or 3307 is already in use, edit `docker compose.yml`:

```yaml
services:
  app:
    ports:
      - "8080:80"  # Changed from 8000
  db:
    ports:
      - "3308:3306"  # Changed from 3307
```

### Reset everything

```bash
# Stop containers and remove volumes
docker compose down -v

# Remove images
docker compose down --rmi all

# Start fresh
./docker-setup.sh
```

## File Structure

```
Egg9_Backend/
├── Dockerfile              # Laravel app container
├── docker compose.yml      # Multi-container setup
├── docker-setup.sh         # Automated setup script
├── .dockerignore          # Docker build exclusions
├── DOCKER_SETUP.md        # This file
└── [Laravel files...]
```

## Performance Tips

### Development Mode

For faster development with hot-reload:

```yaml
# In docker compose.yml, add to app service:
volumes:
  - .:/var/www/html:cached  # Add :cached for macOS
```

### Production Build

For production deployment:

```bash
# Build optimized image
docker compose -f docker compose.prod.yml build

# Run with production settings
docker compose -f docker compose.prod.yml up -d
```

## Database Persistence

MySQL data is stored in a Docker volume named `mysql_data`. This persists between container restarts.

To completely reset the database:

```bash
docker compose down -v  # -v removes volumes
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
```

## Connecting from Host Machine

### Using MySQL Client

```bash
mysql -h 127.0.0.1 -P 3307 -u egg9_user -pegg9_password egg9
```

### Using Database GUI (e.g., TablePlus, DBeaver)

- Host: `127.0.0.1`
- Port: `3307`
- Database: `egg9`
- Username: `egg9_user`
- Password: `egg9_password`

## Weekly Automation (Cron)

To enable the weekly subscription processing:

```bash
# Add cron job to app container
docker compose exec app bash -c "echo '* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1' | crontab -"
```

Or manually test:

```bash
docker compose exec app php artisan egg9:process-weekly-cycle --force
```

## Updating the Application

```bash
# Pull latest code
git pull

# Rebuild and restart
docker compose down
docker compose build
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate
```

## Advantages of Docker Setup

✅ **Consistent Environment** - Same setup on all machines
✅ **All PHP Extensions** - Pre-installed and configured
✅ **Database Included** - MySQL ready to use
✅ **Easy Reset** - Start fresh anytime
✅ **Isolated** - Doesn't affect host system
✅ **Production-Ready** - Same config can deploy anywhere

## Next Steps

1. ✅ Start containers: `./docker-setup.sh`
2. ✅ Test API: `curl http://localhost:8000/api/weekly-stock`
3. ✅ Run tests: `docker compose exec app php artisan test`
4. ✅ Import Postman collection: `Egg9_API.postman_collection.json`
5. ✅ Connect React Native frontend to `http://localhost:8000/api`

## Support

For more information:
- Main README: `README.md`
- Testing Guide: `TESTING.md`
- Frontend Integration: `FRONTEND_INTEGRATION.md`

---

**Happy coding! 🐳**

