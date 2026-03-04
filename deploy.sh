#!/bin/bash

# =============================================================================
# Suitescape PH - Deployment Script
# =============================================================================
# Run this after the server setup to deploy the Laravel application
# Usage: bash deploy.sh
# =============================================================================

set -e

APP_DIR="/var/www/suitescape-api"
REPO_URL="https://github.com/kevinpauneljohn/suitescape-api.git"

echo "=============================================="
echo "  Suitescape PH - Deployment Script"
echo "=============================================="

# =============================================================================
# STEP 1: Clone or Pull Repository
# =============================================================================
echo ""
echo "Step 1: Getting latest code..."

if [ -d "$APP_DIR/.git" ]; then
    cd $APP_DIR
    git pull origin main
else
    # Backup any existing files
    if [ -d "$APP_DIR" ]; then
        mv $APP_DIR ${APP_DIR}_backup_$(date +%Y%m%d_%H%M%S)
    fi
    git clone $REPO_URL $APP_DIR
    cd $APP_DIR
fi

echo "[✓] Code updated"

# =============================================================================
# STEP 2: Install Dependencies
# =============================================================================
echo ""
echo "Step 2: Installing dependencies..."

composer install --no-dev --optimize-autoloader --no-interaction
echo "[✓] Composer dependencies installed"

# =============================================================================
# STEP 3: Setup Environment File
# =============================================================================
echo ""
echo "Step 3: Setting up environment..."

if [ ! -f "$APP_DIR/.env" ]; then
    cp $APP_DIR/.env.example $APP_DIR/.env
    
    # Generate application key
    php artisan key:generate --force
    
    echo ""
    echo "=================================================="
    echo "  IMPORTANT: Configure your .env file!"
    echo "=================================================="
    echo ""
    echo "Edit /var/www/suitescape-api/.env and update:"
    echo ""
    echo "  APP_URL=http://72.62.252.179"
    echo "  APP_ENV=production"
    echo "  APP_DEBUG=false"
    echo ""
    echo "  DB_DATABASE=suitescape_api"
    echo "  DB_USERNAME=suitescape"
    echo "  DB_PASSWORD=SuitesSc@pe2024!"
    echo ""
    echo "  BROADCAST_DRIVER=pusher"
    echo "  CACHE_DRIVER=redis"
    echo "  QUEUE_CONNECTION=redis"
    echo "  SESSION_DRIVER=redis"
    echo ""
    echo "  # Add your Pusher credentials"
    echo "  # Add your Paymongo credentials"
    echo "  # Add your Mail credentials"
    echo ""
    echo "Press Enter after you've configured the .env file..."
    read
fi

echo "[✓] Environment configured"

# =============================================================================
# STEP 4: Set Permissions
# =============================================================================
echo ""
echo "Step 4: Setting permissions..."

chown -R www-data:www-data $APP_DIR
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

echo "[✓] Permissions set"

# =============================================================================
# STEP 5: Run Migrations
# =============================================================================
echo ""
echo "Step 5: Running database migrations..."

php artisan migrate --force
echo "[✓] Migrations completed"

# =============================================================================
# STEP 6: Create Storage Link
# =============================================================================
echo ""
echo "Step 6: Creating storage link..."

php artisan storage:link --force
echo "[✓] Storage link created"

# =============================================================================
# STEP 7: Optimize Application
# =============================================================================
echo ""
echo "Step 7: Optimizing application..."

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[✓] Application optimized"

# =============================================================================
# STEP 8: Restart Services
# =============================================================================
echo ""
echo "Step 8: Restarting services..."

systemctl restart php8.2-fpm
systemctl restart nginx
supervisorctl restart all

echo "[✓] Services restarted"

# =============================================================================
# DONE
# =============================================================================
echo ""
echo "=============================================="
echo "  Deployment Complete!"
echo "=============================================="
echo ""
echo "Your API should now be accessible at:"
echo "  http://72.62.252.179/api"
echo ""
echo "API Documentation:"
echo "  http://72.62.252.179/docs/api"
echo ""
