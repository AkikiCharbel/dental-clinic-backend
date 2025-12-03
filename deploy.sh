#!/bin/bash

# =============================================================================
# Dental Clinic Backend - Deployment Script
# =============================================================================
# This script is designed for Laravel Forge or similar deployment platforms.
# It handles zero-downtime deployments with proper cache clearing and optimization.
# =============================================================================

set -e

echo "🚀 Starting deployment..."

# Enter maintenance mode
echo "📦 Entering maintenance mode..."
php artisan down --retry=60 --refresh=5 || true

# Pull latest changes (if using git-based deployment)
# git pull origin main

# Install/update Composer dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Run database migrations
echo "🗃️ Running migrations..."
php artisan migrate --force

# Clear and cache configurations
echo "🔧 Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Clear old caches
php artisan cache:clear

# Restart queue workers (Horizon)
echo "🔄 Restarting queue workers..."
php artisan horizon:terminate || true

# Generate API documentation (optional - can be commented out)
# php artisan scribe:generate --no-interaction

# Set correct permissions
echo "🔐 Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Exit maintenance mode
echo "✅ Bringing application back online..."
php artisan up

echo "🎉 Deployment completed successfully!"
