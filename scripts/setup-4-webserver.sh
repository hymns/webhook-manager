#!/bin/bash

#######################################
# Git Webhook Manager - Web Server Setup
# Automates Phase 4: Nginx configuration, SSL, and service startup
#######################################

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}$1${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
}

# Check if running with sudo
if [ "$EUID" -ne 0 ]; then 
    print_error "Please run this script with sudo"
    echo "Usage: sudo bash scripts/setup-webserver.sh"
    exit 1
fi

print_header "Git Webhook Manager - Web Server Setup"

# Get application directory (assumes script is in scripts/ folder)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"

print_info "Application directory: $APP_DIR"

# Verify we're in the correct directory
if [ ! -f "$APP_DIR/artisan" ]; then
    print_error "artisan file not found. Are you running from the correct directory?"
    exit 1
fi

# Get domain name from user
echo ""
read -p "Enter your domain name (e.g., webhook.example.com): " DOMAIN_NAME
if [ -z "$DOMAIN_NAME" ]; then
    print_error "Domain name is required!"
    exit 1
fi

# Ask for www subdomain
read -p "Include www subdomain? (y/n, default: n): " INCLUDE_WWW
INCLUDE_WWW=${INCLUDE_WWW:-n}

# Build server_name directive
if [[ "$INCLUDE_WWW" == "y" || "$INCLUDE_WWW" == "Y" ]]; then
    SERVER_NAME="$DOMAIN_NAME www.$DOMAIN_NAME"
    SSL_DOMAINS="-d $DOMAIN_NAME -d www.$DOMAIN_NAME"
else
    SERVER_NAME="$DOMAIN_NAME"
    SSL_DOMAINS="-d $DOMAIN_NAME"
fi

# Ask for SSL setup
read -p "Setup SSL certificate with Certbot? (y/n, default: y): " SETUP_SSL
SETUP_SSL=${SETUP_SSL:-y}

if [[ "$SETUP_SSL" == "y" || "$SETUP_SSL" == "Y" ]]; then
    read -p "Enter email for SSL certificate notifications: " SSL_EMAIL
    if [ -z "$SSL_EMAIL" ]; then
        print_warning "Email not provided. Using default: admin@$DOMAIN_NAME"
        SSL_EMAIL="admin@$DOMAIN_NAME"
    fi
fi

# Detect PHP version
print_info "Detecting installed PHP versions..."
PHP_VERSION=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+' | head -1)
if [ -z "$PHP_VERSION" ]; then
    print_error "Could not detect PHP version"
    exit 1
fi
print_success "Detected PHP version: $PHP_VERSION"

# Verify PHP-FPM socket exists
PHP_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
if [ ! -S "$PHP_SOCKET" ]; then
    print_warning "PHP-FPM socket not found at $PHP_SOCKET"
    print_info "Starting PHP-FPM service..."
    systemctl start php${PHP_VERSION}-fpm
    systemctl enable php${PHP_VERSION}-fpm
fi

print_header "Step 1: Creating Nginx Configuration"

# Create Nginx config
NGINX_CONFIG="/etc/nginx/sites-available/webhook-manager"
print_info "Creating Nginx config at: $NGINX_CONFIG"

cat > "$NGINX_CONFIG" << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_NAME;
    root $APP_DIR/public;

    index index.php index.html;
    charset utf-8;

    # Logging
    access_log /var/log/nginx/webhook-manager-access.log;
    error_log /var/log/nginx/webhook-manager-error.log;

    # Security: Limit request body size
    client_max_body_size 100M;
    client_body_buffer_size 128k;

    # Security: Timeouts
    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 15;
    send_timeout 10;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    
    # Hide Nginx version
    server_tokens off;

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        fastcgi_pass unix:$PHP_SOCKET;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Security: Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Security: Deny access to sensitive files
    location ~* \.(env|log|md|sql|sqlite|conf|ini|bak|old|tmp|swp)\$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Security: Deny access to common exploit files
    location ~* (\.(git|svn|hg|bzr)|composer\.(json|lock)|package(-lock)?\.json|Dockerfile|nginx\.conf)\$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Optimize: Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Security: Disable logging for favicon and robots
    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    # Error page
    error_page 404 /index.php;
}
EOF

print_success "Nginx configuration created"

print_header "Step 2: Enabling Site"

# Create symlink if it doesn't exist
if [ ! -L "/etc/nginx/sites-enabled/webhook-manager" ]; then
    print_info "Creating symlink..."
    ln -s /etc/nginx/sites-available/webhook-manager /etc/nginx/sites-enabled/
    print_success "Site enabled"
else
    print_info "Site already enabled"
fi

# Test Nginx configuration
print_info "Testing Nginx configuration..."
if nginx -t 2>&1 | grep -q "successful"; then
    print_success "Nginx configuration test passed"
else
    print_error "Nginx configuration test failed!"
    nginx -t
    exit 1
fi

# Reload Nginx
print_info "Reloading Nginx..."
systemctl reload nginx
print_success "Nginx reloaded"

print_header "Step 3: SSL Certificate Setup"

if [[ "$SETUP_SSL" == "y" || "$SETUP_SSL" == "Y" ]]; then
    # Check if certbot is installed
    if ! command -v certbot &> /dev/null; then
        print_error "Certbot is not installed!"
        print_info "Please run: sudo apt install certbot python3-certbot-nginx"
        exit 1
    fi

    print_info "Requesting SSL certificate from Let's Encrypt..."
    print_warning "Make sure your domain is pointing to this server!"
    
    # Request certificate
    if certbot --nginx $SSL_DOMAINS --non-interactive --agree-tos --email "$SSL_EMAIL" --redirect; then
        print_success "SSL certificate installed successfully!"
        print_info "Certificate will auto-renew via systemd timer"
    else
        print_warning "SSL certificate request failed. You can try again later with:"
        echo "sudo certbot --nginx $SSL_DOMAINS"
    fi
else
    print_info "Skipping SSL setup"
    print_warning "Your site will be accessible via HTTP only"
    print_info "To setup SSL later, run:"
    echo "sudo certbot --nginx $SSL_DOMAINS --email your@email.com"
fi

print_header "Step 4: Starting Services"

# Start queue workers
print_info "Starting queue workers..."
supervisorctl reread > /dev/null 2>&1
supervisorctl update > /dev/null 2>&1

if supervisorctl start webhook-manager-queue:* > /dev/null 2>&1; then
    print_success "Queue workers started"
else
    print_warning "Queue workers may already be running"
fi

# Start scheduler
if supervisorctl start webhook-manager-scheduler:* > /dev/null 2>&1; then
    print_success "Scheduler started"
else
    print_warning "Scheduler may already be running"
fi

print_header "Step 5: Verification"

# Check services
print_info "Checking service status..."
echo ""

# Check Nginx
if systemctl is-active --quiet nginx; then
    print_success "✓ Nginx is running"
else
    print_error "✗ Nginx is not running"
fi

# Check PHP-FPM
if systemctl is-active --quiet php${PHP_VERSION}-fpm; then
    print_success "✓ PHP-FPM ${PHP_VERSION} is running"
else
    print_error "✗ PHP-FPM ${PHP_VERSION} is not running"
fi

# Check queue workers
QUEUE_STATUS=$(supervisorctl status webhook-manager-queue:* 2>&1 | grep RUNNING | wc -l)
if [ "$QUEUE_STATUS" -gt 0 ]; then
    print_success "✓ Queue workers are running ($QUEUE_STATUS workers)"
else
    print_warning "✗ Queue workers are not running"
fi

# Check scheduler
if supervisorctl status webhook-manager-scheduler:* 2>&1 | grep -q RUNNING; then
    print_success "✓ Scheduler is running"
else
    print_warning "✗ Scheduler is not running"
fi

echo ""
print_header "Setup Complete!"

echo ""
if [[ "$SETUP_SSL" == "y" || "$SETUP_SSL" == "Y" ]]; then
    print_success "Your application is now live at: https://$DOMAIN_NAME"
else
    print_success "Your application is now live at: http://$DOMAIN_NAME"
fi

echo ""
print_info "Next steps:"
echo "1. Visit your domain in a browser"
echo "2. Create admin user: sudo -u www-data php artisan tinker"
echo "3. Check logs: tail -f $APP_DIR/storage/logs/laravel.log"
echo "4. Monitor queue: sudo supervisorctl status"

echo ""
print_info "Configuration files:"
echo "  Nginx: /etc/nginx/sites-available/webhook-manager"
echo "  Logs:  /var/log/nginx/webhook-manager-*.log"
if [[ "$SETUP_SSL" == "y" || "$SETUP_SSL" == "Y" ]]; then
    echo "  SSL:   /etc/letsencrypt/live/$DOMAIN_NAME/"
fi

echo ""
print_success "All done! 🎉"
echo ""
