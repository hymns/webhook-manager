#!/bin/bash

#########################################################
# Webhook Manager - Ubuntu Setup Script
# Automates installation of all prerequisites
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

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_header() {
    echo ""
    echo -e "${GREEN}==========================================${NC}"
    echo -e "${GREEN}$1${NC}"
    echo -e "${GREEN}==========================================${NC}"
    echo ""
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root or with sudo"
        exit 1
    fi
}

# Main setup
print_info "Starting Webhook Manager prerequisite installation..."
echo ""

# Check if running as root
check_root

# Update system
print_info "Updating system packages..."
apt-get update -y > /dev/null 2>&1
apt-get upgrade -y > /dev/null 2>&1
print_success "System updated"

# Install basic dependencies
print_info "Installing basic dependencies..."
apt-get install -y software-properties-common apt-transport-https ca-certificates \
    curl wget git unzip build-essential gnupg2 lsb-release > /dev/null 2>&1
print_success "Basic dependencies installed"

# Install Nginx
print_info "Installing Nginx..."
apt-get install -y nginx > /dev/null 2>&1
systemctl enable nginx > /dev/null 2>&1
systemctl start nginx > /dev/null 2>&1
print_success "Nginx installed and started"

# Add PHP repository
print_info "Adding PHP repository..."
add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
apt-get update -y > /dev/null 2>&1
print_success "PHP repository added"

# Install multiple PHP versions
print_info "Installing PHP versions (7.4, 8.0, 8.1, 8.2, 8.3, 8.4)..."
for version in 7.4 8.0 8.1 8.2 8.3 8.4; do
    print_info "Installing PHP $version..."
    apt-get install -y \
        php${version}-fpm \
        php${version}-cli \
        php${version}-common \
        php${version}-mysql \
        php${version}-pgsql \
        php${version}-sqlite3 \
        php${version}-zip \
        php${version}-gd \
        php${version}-mbstring \
        php${version}-curl \
        php${version}-xml \
        php${version}-bcmath \
        php${version}-intl \
        php${version}-redis > /dev/null 2>&1
    systemctl enable php${version}-fpm > /dev/null 2>&1
    systemctl start php${version}-fpm > /dev/null 2>&1
    print_success "PHP $version installed"
done

# Install Composer
print_info "Installing Composer..."
curl -sS https://getcomposer.org/installer | php > /dev/null 2>&1
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
print_success "Composer installed"

# Install Node.js from NodeSource repository
print_info "Adding NodeSource repository for Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
print_success "NodeSource repository added"

print_info "Installing Node.js 20..."
apt-get install -y nodejs > /dev/null 2>&1
NODE_VERSION=$(node -v 2>/dev/null)
NPM_VERSION=$(npm -v 2>/dev/null)
print_success "Node.js $NODE_VERSION and npm $NPM_VERSION installed"

# Install PM2 globally
print_info "Installing PM2..."
npm install -g pm2 > /dev/null 2>&1
pm2 startup systemd > /dev/null 2>&1
print_success "PM2 installed"

# Install Redis
print_info "Installing Redis..."
apt-get install -y redis-server > /dev/null 2>&1
systemctl enable redis-server > /dev/null 2>&1
systemctl start redis-server > /dev/null 2>&1
print_success "Redis installed and started"

# Install MySQL
print_info "Installing MySQL..."
apt-get install -y mysql-server > /dev/null 2>&1
systemctl enable mysql > /dev/null 2>&1
systemctl start mysql > /dev/null 2>&1
print_success "MySQL installed and started"

# Install Certbot for SSL
print_info "Installing Certbot..."
apt-get install -y certbot python3-certbot-nginx > /dev/null 2>&1
print_success "Certbot installed"

# Install Supervisor for process management
print_info "Installing Supervisor..."
apt-get install -y supervisor > /dev/null 2>&1
systemctl enable supervisor > /dev/null 2>&1
systemctl start supervisor > /dev/null 2>&1
print_success "Supervisor installed and started"

# Create web directories
print_info "Creating web directories..."
mkdir -p /var/www
chown -R www-data:www-data /var/www
chmod -R 755 /var/www
print_success "Web directories created"

# Install WP-CLI (WordPress command-line tool)
print_info "Installing WP-CLI..."
if ! command -v wp &> /dev/null; then
    curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /tmp/wp-cli.phar 2>/dev/null
    if [ -f /tmp/wp-cli.phar ]; then
        chmod +x /tmp/wp-cli.phar
        mv /tmp/wp-cli.phar /usr/local/bin/wp
        print_success "WP-CLI installed (for 1-click WordPress deployment)"
    else
        print_warning "WP-CLI download failed - WordPress deployment feature won't work"
    fi
else
    print_success "WP-CLI already installed"
fi

# Create PM2 config directory
print_info "Creating PM2 config directory..."
mkdir -p /etc/pm2
chmod 755 /etc/pm2
print_success "PM2 config directory created"

# Security Hardening
print_header "Security Hardening"

# Install fail2ban for brute-force protection
print_info "Installing fail2ban (brute-force protection)..."
if apt-get install -y fail2ban > /dev/null 2>&1; then
    systemctl enable fail2ban > /dev/null 2>&1
    systemctl start fail2ban > /dev/null 2>&1
    print_success "fail2ban installed and enabled"
else
    print_warning "Failed to install fail2ban"
fi

# Configure UFW Firewall
print_info "Configuring UFW firewall..."
if command -v ufw &> /dev/null; then
    # Allow essential services
    ufw --force enable > /dev/null 2>&1
    ufw default deny incoming > /dev/null 2>&1
    ufw default allow outgoing > /dev/null 2>&1
    ufw allow ssh > /dev/null 2>&1
    ufw allow 'Nginx Full' > /dev/null 2>&1
    
    # Database ports (commented out by default for security)
    # Uncomment these if you need remote database access
    # ufw allow 3306/tcp  # MySQL
    # ufw allow 6379/tcp  # Redis
    
    print_success "UFW firewall configured (SSH, HTTP, HTTPS allowed)"
    print_info "Database ports (3306, 6379) are blocked by default"
    print_info "Use Firewall module in web UI to allow specific IPs if needed"
else
    print_warning "UFW not found, skipping firewall configuration"
fi

# Secure MySQL installation
print_info "Securing MySQL installation..."
if command -v mysql &> /dev/null; then
    # Generate random root password
    MYSQL_ROOT_PASS=$(openssl rand -base64 32)
    
    # Secure MySQL without interactive prompts
    mysql --user=root <<_EOF_ > /dev/null 2>&1
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASS}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
_EOF_
    
    if [ $? -eq 0 ]; then
        # Save root password to file
        echo "$MYSQL_ROOT_PASS" > /root/.mysql_root_password
        chmod 600 /root/.mysql_root_password
        print_success "MySQL secured (root password saved to /root/.mysql_root_password)"
    else
        print_warning "MySQL security configuration failed - run mysql_secure_installation manually"
    fi
else
    print_warning "MySQL not found, skipping security configuration"
fi

print_success "Security hardening complete"
echo ""

print_info "⚠️  IMPORTANT SECURITY NOTES:"
echo ""
echo "1. MySQL Root Password:"
echo "   Password saved to: /root/.mysql_root_password"
echo "   Keep this file secure! You'll need it for setup-app.sh"
echo ""
echo "2. Configure SSH security (recommended):"
echo "   sudo nano /etc/ssh/sshd_config"
echo "   - Set PermitRootLogin no"
echo "   - Set PasswordAuthentication no (if using keys)"
echo "   - Set Port 2222 (optional, non-standard port)"
echo "   sudo systemctl restart sshd"
echo ""
echo "3. Review fail2ban configuration:"
echo "   sudo nano /etc/fail2ban/jail.local"
echo ""

print_header "Installation Complete!"

print_info "✅ Installed components:"
echo "  • Nginx web server"
echo "  • PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 with PHP-FPM"
echo "  • Composer (PHP package manager)"
echo "  • Node.js 20 with npm (system-wide)"
echo "  • PM2 (Node.js process manager)"
echo "  • Redis (cache & queue backend)"
echo "  • MySQL (database server - SECURED)"
echo "  • Supervisor (process management)"
echo "  • Certbot (SSL certificates)"
echo "  • WP-CLI (WordPress management)"
echo "  • fail2ban (brute-force protection)"
echo "  • UFW firewall (configured)"
echo ""
print_info "🔒 Security configured:"
echo "  • MySQL root password: /root/.mysql_root_password"
echo "  • UFW firewall: SSH, HTTP, HTTPS allowed"
echo "  • fail2ban: Enabled and monitoring"
echo ""
print_info "📋 Next steps:"
echo "  1. sudo bash scripts/setup-2-sudoers.sh"
echo "  2. Clone your app to /var/www/webhook-manager"
echo "  3. sudo -u www-data bash scripts/setup-3-app.sh"
echo "  4. sudo bash scripts/setup-4-webserver.sh"
echo ""
print_info "⏱️  Total time: ~15-20 minutes"
echo ""
