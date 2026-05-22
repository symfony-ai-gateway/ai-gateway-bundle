#!/bin/sh

cd /runtime

mkdir -p /runtime/data

if [ -f .env.local ]; then
    rm -f .env.local
fi

if [ ! -f /runtime/data/auth.db ]; then
    echo "Initializing database..."
    php bin/console doctrine:database:create --if-not-exists 2>/dev/null || true
fi

echo "Updating database schema..."
php bin/console doctrine:schema:update --force 2>&1 || true

echo "Creating tables if missing..."
php bin/console doctrine:schema:create 2>&1 || true

chown -R www-data:www-data /runtime/data

exec "$@"
