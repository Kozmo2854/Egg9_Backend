#!/bin/bash

echo "🐳 Egg9 Backend - Docker Setup"
echo "================================"
echo ""

# Stop any existing containers
echo "📦 Stopping any existing containers..."
docker compose down 2>/dev/null

# Setup Docker environment file
echo "📝 Setting up environment file..."
./setup-docker-env.sh

# Build and start containers
echo "🔨 Building Docker containers..."
docker compose build

echo "🚀 Starting containers..."
docker compose up -d

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# Install Composer dependencies
echo "📚 Installing Composer dependencies..."
docker compose exec app composer install --no-interaction

# Generate application key
echo "🔑 Generating application key..."
docker compose exec app php artisan key:generate

# Run migrations and seeders
echo "🗄️  Running database migrations..."
docker compose exec app php artisan migrate:fresh --seed --force

# Set permissions
echo "🔐 Setting permissions..."
docker compose exec app chown -R www-data:www-data /var/www/html/storage
docker compose exec app chmod -R 755 /var/www/html/storage
docker compose exec app chmod -R 755 /var/www/html/bootstrap/cache

echo ""
echo "✅ Docker setup complete!"
echo ""
echo "================================"
echo "📊 Services Status:"
echo "================================"
docker compose ps
echo ""
echo "================================"
echo "🎯 Quick Commands:"
echo "================================"
echo "  View logs:        docker compose logs -f"
echo "  Stop containers:  docker compose down"
echo "  Start containers: docker compose up -d"
echo "  Run tests:        docker compose exec app php artisan test"
echo "  Run migrations:   docker compose exec app php artisan migrate"
echo "  Enter app shell:  docker compose exec app bash"
echo "  Enter DB shell:   docker compose exec db mysql -u egg9_user -pegg9_password egg9"
echo ""
echo "================================"
echo "🌐 Access Points:"
echo "================================"
echo "  API:              http://localhost:8000/api"
echo "  Test Login:       admin@egg9.com / password123"
echo "  MySQL (external): localhost:3307"
echo ""
echo "================================"
echo "🧪 Test the API:"
echo "================================"
echo "curl -X POST http://localhost:8000/api/login \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"email\":\"admin@egg9.com\",\"password\":\"password123\"}'"
echo ""

