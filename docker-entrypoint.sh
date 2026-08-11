#!/bin/sh
set -e
set -x

cd /var/www/html

echo "📁 Ensuring required directories exist..."
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

echo "🔐 Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan view:clear || true
php artisan route:clear || true

echo "🔧 Caching config..."
php artisan config:cache || true

echo "🗄️ Running migrations..."
php artisan migrate --force || true

echo "🔗 Linking storage..."
php artisan storage:link || true

echo "🔍 Syncing Scout indexes..."
php artisan scout:flush "App\\Models\\Product" || true
php artisan scout:import "App\\Models\\Product" || true

# Execute passed command if present, otherwise default to Apache
if [ "$#" -gt 0 ]; then
    echo "🚀 Executing custom command: $@"
    exec "$@"
else
    echo "🚀 Starting Apache..."
    exec apache2-foreground
fi