#!/bin/bash

#########################################################
# Webhook Manager - Sudoers Configuration Script
# Sets up passwordless sudo for required commands
#########################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}→ $1${NC}"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root or with sudo"
        exit 1
    fi
}

# Main setup
print_info "Configuring sudoers for Webhook Manager..."
echo ""

# Check if running as root
check_root

# Get web server user
WEB_USER=${1:-www-data}
print_info "Using web server user: $WEB_USER"

# Create sudoers file
SUDOERS_FILE="/etc/sudoers.d/webhook-manager-manager"

print_info "Creating sudoers configuration..."

cat > "$SUDOERS_FILE" << EOF
# Webhook Manager - Automated Management Permissions
# Web server user: $WEB_USER

# Nginx Management
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start nginx
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop nginx
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx

# Certbot - SSL Certificate Management
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/certbot
$WEB_USER ALL=(ALL) NOPASSWD: /snap/bin/certbot

# PHP-FPM Pool Management - Specific versions
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm7.4 -t *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm8.0 -t *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm8.1 -t *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm8.2 -t *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm8.3 -t *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm8.4 -t *
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start php7.4-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start php8.0-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start php8.1-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start php8.2-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start php8.3-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start php8.4-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop php7.4-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop php8.0-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop php8.1-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop php8.2-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop php8.3-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop php8.4-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php7.4-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.0-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.1-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.2-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.3-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.4-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart php7.4-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.0-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.1-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.2-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.3-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.4-fpm

# Directory Management - PHP-FPM logs
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php7.4-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php8.0-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php8.1-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php8.2-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php8.3-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php8.4-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chown -R $WEB_USER?$WEB_USER /var/log/php[78].[0-9]*-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/touch /var/log/php[78].[0-9]*-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/touch /var/log/php-fpm.log
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chown $WEB_USER?$WEB_USER /var/log/php-fpm.log
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod [0-9]* /var/log/php[78].[0-9]*-fpm[/]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod [0-9]* /var/log/php-fpm.log

# File Management - PHP-FPM Pool Config Files (supports domain names with dots, underscores, hyphens)
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf

# File Management - Nginx Config Files (supports domain names with dots, underscores, hyphens)
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/nginx/sites-available/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/nginx/sites-available/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/ln -sf /etc/nginx/sites-available/[a-zA-Z0-9._-]* /etc/nginx/sites-enabled/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/nginx/sites-available/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/nginx/sites-enabled/[a-zA-Z0-9._-]*

# Webroot Directory Management (supports letters, numbers, underscores, dots, hyphens)
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/[a-zA-Z0-9._-]*/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chown -R [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/www/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod -R [0-9]* /var/www/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -rf /var/www/[a-zA-Z0-9._-]*

# PM2 Configuration Management (Node.js - supports domain names with dots, underscores, hyphens)
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /etc/pm2
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 755 /etc/pm2
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/pm2/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/pm2/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/pm2/[a-zA-Z0-9._-]*

# PM2 Process Control (Node.js)
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/pm2
$WEB_USER ALL=(ALL) NOPASSWD: /usr/local/bin/pm2

# Supervisor - Process Manager
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/webhook-manager-*.conf /etc/supervisor/conf.d/
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl reread
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl update
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl start *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl stop *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl status

# Service Management (systemctl for Service Manager module)
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl status *
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl is-active *
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl is-enabled *
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start supervisor
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop supervisor
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart supervisor
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload supervisor
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start redis-server
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop redis-server
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart redis-server
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start mysql
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop mysql
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart mysql
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start fail2ban
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop fail2ban
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart fail2ban
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload fail2ban
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start ufw
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop ufw
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart ufw

# Journal logs access for Service Manager
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/journalctl -u * -n * --no-pager

# Process monitoring for Service Manager
$WEB_USER ALL=(ALL) NOPASSWD: /bin/ps -p * -o *

# Git - Allow all git commands
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/git

# Bash - For deployment scripts (use with caution)
$WEB_USER ALL=(ALL) NOPASSWD: /bin/bash /var/www/[a-z]*/deploy.sh
$WEB_USER ALL=(ALL) NOPASSWD: /bin/bash /var/www/[a-z]*/deployment/deploy.sh

# UFW Firewall Management
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/ufw
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/ufw *

# Crontab Management
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/crontab
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/crontab *

# Log File Access - For Log Viewer
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/tail -n * /var/log/*
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/tail /var/log/*
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/cat /var/log/*
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/truncate -s 0 *
EOF

# Setup PHP-FPM logs
print_info "PHP-FPM Log Setup"
echo ""
print_info "Setting up PHP-FPM log files and directories..."

# Create main PHP-FPM log file
if [ ! -f "/var/log/php-fpm.log" ]; then
    print_info "Creating /var/log/php-fpm.log..."
    sudo touch /var/log/php-fpm.log > /dev/null 2>&1
    sudo chown www-data:www-data /var/log/php-fpm.log > /dev/null 2>&1
    sudo chmod 644 /var/log/php-fpm.log > /dev/null 2>&1
    print_success "Created /var/log/php-fpm.log"
else
    print_info "/var/log/php-fpm.log already exists"
fi

# Detect and setup logs for all installed PHP versions
for php_version in $(ls -d /etc/php/*/ 2>/dev/null | grep -oP '\d+\.\d+' | sort -u); do
    log_dir="/var/log/php${php_version}-fpm"
    
    if [ ! -d "$log_dir" ]; then
        print_info "Creating log directory for PHP ${php_version}..."
        sudo mkdir -p "$log_dir" > /dev/null 2>&1
        sudo chown www-data:www-data "$log_dir" > /dev/null 2>&1
        sudo chmod 755 "$log_dir" > /dev/null 2>&1
        print_success "Created $log_dir"
    else
        print_info "Log directory for PHP ${php_version} already exists"
    fi
done

print_success "PHP-FPM logs configured"
echo ""

# Set proper permissions
chmod 0440 "$SUDOERS_FILE"
print_success "Sudoers file created: $SUDOERS_FILE"

# Validate sudoers syntax
print_info "Validating sudoers syntax..."
if visudo -c -f "$SUDOERS_FILE"; then
    print_success "Sudoers configuration is valid"
else
    print_error "Sudoers configuration has errors!"
    rm -f "$SUDOERS_FILE"
    exit 1
fi

# Test sudo access
print_info "Testing sudo access for $WEB_USER..."
if sudo -u $WEB_USER sudo -n nginx -t &>/dev/null; then
    print_success "Sudo access test passed"
else
    print_error "Sudo access test failed - nginx test command"
fi

echo ""
print_success "=========================================="
print_success "Sudoers configuration completed!"
print_success "=========================================="
echo ""
print_info "Configured permissions for: $WEB_USER"
echo ""
print_info "Allowed commands:"
echo "  • Nginx management (reload, restart, test)"
echo "  • SSL certificate management (certbot)"
echo "  • PHP-FPM pool management (versions 7.4, 8.0-8.4)"
echo "  • Nginx configuration file management"
echo "  • PM2 process management (Node.js)"
echo "  • Supervisor process management (queue workers, scheduler)"
echo "  • UFW firewall management"
echo "  • Crontab management"
echo "  • Git deployments"
echo "  • Deployment script execution"
echo "  • Log file access (tail, cat, truncate for Log Viewer)"
echo ""
print_info "Security file: $SUDOERS_FILE"
print_info "Permissions: 0440 (read-only)"
echo ""
