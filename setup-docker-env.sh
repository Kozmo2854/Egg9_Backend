#!/bin/bash
# Setup environment file for Docker

echo "Setting up Docker environment file..."

cat > .env << 'ENVFILE'
APP_NAME=Egg9
APP_ENV=local
APP_KEY=base64:v4wtW7NFs98n9SMW/qgfCX0g9GcIwNBFfd/tJbW13fc=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=egg9
DB_USERNAME=egg9_user
DB_PASSWORD=egg9_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000,::1

BCRYPT_ROUNDS=4
ENVFILE

echo "✓ .env file created successfully!"
