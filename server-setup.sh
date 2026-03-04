#!/bin/bash

# =============================================================================
# Suitescape PH - VPS Server Setup Script
# =============================================================================
# This script sets up a fresh Ubuntu/Debian server for Laravel deployment
# Run as root: bash server-setup.sh
# =============================================================================

set -e

echo "=============================================="
echo "  Suitescape PH - Server Setup Script"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# =============================================================================
# STEP 1: System Update
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 1: Updating System Packages"
echo "=============================================="

apt update && apt upgrade -y
print_status "System packages updated"

# =============================================================================
# STEP 2: Install Essential Packages
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 2: Installing Essential Packages"
echo "=============================================="

apt install -y \
    curl \
    wget \
    git \
    unzip \
    zip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    supervisor \
    cron \
    htop \
    nano \
    vim

print_status "Essential packages installed"

# =============================================================================
# STEP 3: Install Nginx
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 3: Installing Nginx"
echo "=============================================="

apt install -y nginx
systemctl start nginx
systemctl enable nginx
print_status "Nginx installed and started"

# =============================================================================
# STEP 4: Install PHP 8.2 with Extensions
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 4: Installing PHP 8.2"
echo "=============================================="

# Add PHP repository
add-apt-repository -y ppa:ondrej/php
apt update

# Install PHP 8.2 and required extensions for Laravel
apt install -y \
    php8.2-fpm \
    php8.2-cli \
    php8.2-common \
    php8.2-mysql \
    php8.2-pgsql \
    php8.2-sqlite3 \
    php8.2-xml \
    php8.2-xmlrpc \
    php8.2-curl \
    php8.2-gd \
    php8.2-imagick \
    php8.2-mbstring \
    php8.2-bcmath \
    php8.2-zip \
    php8.2-intl \
    php8.2-readline \
    php8.2-soap \
    php8.2-redis \
    php8.2-tokenizer \
    php8.2-fileinfo \
    php8.2-opcache

# Start PHP-FPM
systemctl start php8.2-fpm
systemctl enable php8.2-fpm

print_status "PHP 8.2 installed with all required extensions"

# =============================================================================
# STEP 5: Configure PHP
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 5: Configuring PHP"
echo "=============================================="

# Update PHP configuration for larger uploads (videos)
PHP_INI="/etc/php/8.2/fpm/php.ini"
PHP_CLI_INI="/etc/php/8.2/cli/php.ini"

# FPM Configuration
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 256M/' $PHP_INI
sed -i 's/post_max_size = .*/post_max_size = 256M/' $PHP_INI
sed -i 's/memory_limit = .*/memory_limit = 512M/' $PHP_INI
sed -i 's/max_execution_time = .*/max_execution_time = 300/' $PHP_INI
sed -i 's/max_input_time = .*/max_input_time = 300/' $PHP_INI

# CLI Configuration
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 256M/' $PHP_CLI_INI
sed -i 's/post_max_size = .*/post_max_size = 256M/' $PHP_CLI_INI
sed -i 's/memory_limit = .*/memory_limit = 512M/' $PHP_CLI_INI
sed -i 's/max_execution_time = .*/max_execution_time = 300/' $PHP_CLI_INI

systemctl restart php8.2-fpm
print_status "PHP configured for large file uploads"

# =============================================================================
# STEP 6: Install Composer
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 6: Installing Composer"
echo "=============================================="

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
print_status "Composer installed"

# =============================================================================
# STEP 7: Install MariaDB (MySQL Alternative)
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 7: Installing MariaDB"
echo "=============================================="

apt install -y mariadb-server mariadb-client
systemctl start mariadb
systemctl enable mariadb

print_status "MariaDB installed and started"

# Secure MariaDB installation
echo ""
print_warning "Running MariaDB secure installation..."
echo "Please follow the prompts to secure your database:"
echo "  - Set root password: Choose a strong password"
echo "  - Remove anonymous users: Y"
echo "  - Disallow root login remotely: Y"
echo "  - Remove test database: Y"
echo "  - Reload privilege tables: Y"
echo ""

mysql_secure_installation

# =============================================================================
# STEP 8: Install FFmpeg (for video processing)
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 8: Installing FFmpeg"
echo "=============================================="

apt install -y ffmpeg
ffmpeg -version | head -1
print_status "FFmpeg installed"

# =============================================================================
# STEP 9: Install Redis (for caching and queues)
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 9: Installing Redis"
echo "=============================================="

apt install -y redis-server
systemctl start redis-server
systemctl enable redis-server
print_status "Redis installed and started"

# =============================================================================
# STEP 10: Install Node.js & NPM (for asset compilation)
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 10: Installing Node.js"
echo "=============================================="

curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
print_status "Node.js $(node -v) installed"

# =============================================================================
# STEP 11: Create Application Directory Structure
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 11: Creating Directory Structure"
echo "=============================================="

# Create web root directory
mkdir -p /var/www/suitescape-api

# Create storage directories for FFmpeg and uploads
mkdir -p /var/www/suitescape-api/storage/app/public/videos
mkdir -p /var/www/suitescape-api/storage/app/public/images
mkdir -p /var/www/suitescape-api/storage/app/public/thumbnails
mkdir -p /var/www/suitescape-api/storage/app/temp
mkdir -p /var/www/suitescape-api/storage/framework/cache
mkdir -p /var/www/suitescape-api/storage/framework/sessions
mkdir -p /var/www/suitescape-api/storage/framework/views
mkdir -p /var/www/suitescape-api/storage/logs

# Set proper ownership
chown -R www-data:www-data /var/www/suitescape-api
chmod -R 775 /var/www/suitescape-api/storage

print_status "Directory structure created"

# =============================================================================
# STEP 12: Configure Nginx for Laravel
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 12: Configuring Nginx"
echo "=============================================="

# Create Nginx configuration for Suitescape
cat > /etc/nginx/sites-available/suitescape-api << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name 72.62.252.179;  # Replace with your domain when available
    root /var/www/suitescape-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    # PHP-FPM Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Deny access to .htaccess files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase client body size for video uploads
    client_max_body_size 256M;

    # Enable gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml application/javascript application/json;
    gzip_disable "MSIE [1-6]\.";
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/suitescape-api /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and reload Nginx
nginx -t && systemctl reload nginx
print_status "Nginx configured for Laravel"

# =============================================================================
# STEP 13: Configure Supervisor for Laravel Queues
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 13: Configuring Supervisor"
echo "=============================================="

cat > /etc/supervisor/conf.d/suitescape-worker.conf << 'EOF'
[program:suitescape-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/suitescape-api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/suitescape-api/storage/logs/worker.log
stopwaitsecs=3600
EOF

supervisorctl reread
supervisorctl update
print_status "Supervisor configured for queue workers"

# =============================================================================
# STEP 14: Setup Firewall (UFW)
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 14: Configuring Firewall"
echo "=============================================="

apt install -y ufw
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 'Nginx Full'
ufw --force enable
print_status "Firewall configured"

# =============================================================================
# STEP 15: Create Database and User
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 15: Creating Database"
echo "=============================================="

print_warning "Creating database and user..."
print_warning "You'll need to enter the MariaDB root password you set earlier"

# Create database script
cat > /tmp/create_db.sql << 'EOF'
CREATE DATABASE IF NOT EXISTS suitescape_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'suitescape'@'localhost' IDENTIFIED BY 'SuitesSc@pe2024!';
GRANT ALL PRIVILEGES ON suitescape_api.* TO 'suitescape'@'localhost';
FLUSH PRIVILEGES;
EOF

mysql -u root -p < /tmp/create_db.sql
rm /tmp/create_db.sql

print_status "Database 'suitescape_api' and user 'suitescape' created"

# =============================================================================
# STEP 16: Setup Cron for Laravel Scheduler
# =============================================================================
echo ""
echo "=============================================="
echo "  Step 16: Setting up Laravel Scheduler"
echo "=============================================="

# Add Laravel scheduler to crontab
(crontab -l 2>/dev/null; echo "* * * * * cd /var/www/suitescape-api && php artisan schedule:run >> /dev/null 2>&1") | crontab -
print_status "Laravel scheduler cron job added"

# =============================================================================
# FINAL SUMMARY
# =============================================================================
echo ""
echo "=============================================="
echo "  Setup Complete!"
echo "=============================================="
echo ""
echo "Server Configuration Summary:"
echo "-------------------------------------------"
echo "  Web Server:      Nginx"
echo "  PHP Version:     8.2"
echo "  Database:        MariaDB"
echo "  Cache:           Redis"
echo "  Queue Worker:    Supervisor"
echo "  Video Processing: FFmpeg"
echo ""
echo "Database Credentials:"
echo "-------------------------------------------"
echo "  Database Name:   suitescape_api"
echo "  Database User:   suitescape"
echo "  Database Pass:   SuitesSc@pe2024!"
echo ""
echo "Important Paths:"
echo "-------------------------------------------"
echo "  Web Root:        /var/www/suitescape-api"
echo "  Nginx Config:    /etc/nginx/sites-available/suitescape-api"
echo "  PHP Config:      /etc/php/8.2/fpm/php.ini"
echo "  Supervisor:      /etc/supervisor/conf.d/suitescape-worker.conf"
echo ""
echo "Next Steps:"
echo "-------------------------------------------"
echo "  1. Deploy your Laravel application to /var/www/suitescape-api"
echo "  2. Configure your .env file"
echo "  3. Run: php artisan migrate --seed"
echo "  4. Run: php artisan storage:link"
echo "  5. Set permissions: chown -R www-data:www-data /var/www/suitescape-api"
echo ""
print_status "Server is ready for deployment!"
