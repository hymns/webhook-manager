#!/bin/bash

#########################################################
# Hostiqo - Complete Installer
# Single script to install everything
# Run with: sudo bash scripts/install.sh
#########################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }
print_info() { echo -e "${YELLOW}→ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }
print_header() {
    echo ""
    echo -e "${BLUE}==========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}==========================================${NC}"
    echo ""
}

# Read input from terminal (works with curl pipe)
read_input() {
    read "$@" </dev/tty || true
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root or with sudo"
        echo "Usage: sudo bash scripts/install.sh"
        exit 1
    fi
}

# Default installation path
DEFAULT_APP_DIR="/var/www/hostiqo"
REPO_URL="https://github.com/hymns/hostiqo.git"
WEB_USER="www-data"

# Will be set after clone/detection
APP_DIR=""

#########################################################
# PHASE 1: System Prerequisites
#########################################################
install_prerequisites() {
    print_header "Phase 1: Installing System Prerequisites"
    
    # Update system
    print_info "Updating system packages..."
    apt-get update -y > /dev/null 2>&1
    apt-get upgrade -y > /dev/null 2>&1
    print_success "System updated"
    
    # Install basic dependencies
    print_info "Installing basic dependencies..."
    apt-get install -y software-properties-common apt-transport-https ca-certificates \
        curl wget git net-tools unzip build-essential gnupg2 lsb-release > /dev/null 2>&1
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
    
    # Install Node.js
    print_info "Adding NodeSource repository for Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    print_info "Installing Node.js 20..."
    apt-get install -y nodejs > /dev/null 2>&1
    print_success "Node.js $(node -v) installed"
    
    # Install PM2
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
    
    # Install Certbot
    print_info "Installing Certbot..."
    apt-get install -y certbot python3-certbot-nginx > /dev/null 2>&1
    print_success "Certbot installed"
    
    # Install Supervisor
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
    
    # Install WP-CLI
    print_info "Installing WP-CLI..."
    if ! command -v wp &> /dev/null; then
        curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /tmp/wp-cli.phar 2>/dev/null
        if [ -f /tmp/wp-cli.phar ]; then
            chmod +x /tmp/wp-cli.phar
            mv /tmp/wp-cli.phar /usr/local/bin/wp
            print_success "WP-CLI installed"
        fi
    else
        print_success "WP-CLI already installed"
    fi
    
    # Create PM2 config directory
    mkdir -p /etc/pm2
    chmod 755 /etc/pm2
    
    # Security Hardening
    print_header "Security Hardening"
    
    # Install fail2ban
    print_info "Installing fail2ban..."
    if apt-get install -y fail2ban > /dev/null 2>&1; then
        systemctl enable fail2ban > /dev/null 2>&1
        systemctl start fail2ban > /dev/null 2>&1
        print_success "fail2ban installed and enabled"
    fi
    
    # Configure UFW
    print_info "Configuring UFW firewall..."
    if command -v ufw &> /dev/null; then
        ufw --force enable > /dev/null 2>&1
        ufw default deny incoming > /dev/null 2>&1
        ufw default allow outgoing > /dev/null 2>&1
        ufw allow ssh > /dev/null 2>&1
        ufw allow 'Nginx Full' > /dev/null 2>&1
        print_success "UFW firewall configured"
    fi
    
    # Secure MySQL
    print_info "Securing MySQL installation..."
    MYSQL_ROOT_PASS=$(openssl rand -base64 32)
    mysql --user=root <<_EOF_ > /dev/null 2>&1 || true
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASS}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
_EOF_
    
    echo "$MYSQL_ROOT_PASS" > /root/.mysql_root_password
    chmod 600 /root/.mysql_root_password
    print_success "MySQL secured (root password: /root/.mysql_root_password)"
    
    print_success "Phase 1 completed!"
}

#########################################################
# PHASE 2: Sudoers Configuration
#########################################################
configure_sudoers() {
    print_header "Phase 2: Configuring Sudoers"
    
    SUDOERS_FILE="/etc/sudoers.d/hostiqo-manager"
    
    print_info "Creating sudoers configuration for $WEB_USER..."
    
    cat > "$SUDOERS_FILE" << EOF
# Hostiqo - Automated Management Permissions
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

# PHP-FPM Pool Management
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start php*-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl stop php*-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php*-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart php*-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm* -t
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm* -t *

# File Management - PHP-FPM Pool Config Files
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf

# File Management - Nginx Config Files
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/nginx/sites-available/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/nginx/sites-available/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/ln -sf /etc/nginx/sites-available/[a-zA-Z0-9._-]* /etc/nginx/sites-enabled/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/nginx/sites-available/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/nginx/sites-enabled/[a-zA-Z0-9._-]*

# Webroot Directory Management
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/[a-zA-Z0-9._-]*/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chown -R [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/www/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chown [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/www/[a-zA-Z0-9._-]*/*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod -R [0-9]* /var/www/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod [0-9]* /var/www/[a-zA-Z0-9._-]*/*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mv /tmp/* /var/www/[a-zA-Z0-9._-]*/*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -rf /var/www/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/find /var/www/[a-zA-Z0-9._-]* *

# Nginx Cache Directory
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/cache/nginx/*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chown -R [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/cache/nginx/*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod -R [0-9]* /var/cache/nginx/*

# PHP-FPM Log Directory
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php*-fpm
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chown [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/log/php*-fpm

# PM2 Process Control
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/pm2
$WEB_USER ALL=(ALL) NOPASSWD: /usr/local/bin/pm2
$WEB_USER ALL=(ALL) NOPASSWD: /bin/mkdir -p /etc/pm2
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/pm2/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/pm2/[a-zA-Z0-9._-]*
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/pm2/[a-zA-Z0-9._-]*

# Supervisor - Process Manager
$WEB_USER ALL=(ALL) NOPASSWD: /bin/cp /tmp/hostiqo-*.conf /etc/supervisor/conf.d/*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/cp /tmp/hostiqo-*.conf /etc/supervisor/conf.d/*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /bin/rm -f /etc/supervisor/conf.d/*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/supervisor/conf.d/*.conf
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl reread
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl update
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl start *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl stop *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart *
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl status
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/tail -n * /var/log/supervisor/*.log

# Service Management
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

# Journal logs
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/journalctl -u * -n * --no-pager

# Git
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/git

# UFW Firewall
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/ufw
$WEB_USER ALL=(ALL) NOPASSWD: /usr/sbin/ufw *

# Crontab
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/crontab
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/crontab *

# Log File Access
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/tail -n * /var/log/*
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/tail /var/log/*
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/cat /var/log/*
$WEB_USER ALL=(ALL) NOPASSWD: /usr/bin/truncate -s 0 *
EOF

    chmod 0440 "$SUDOERS_FILE"
    
    # Validate sudoers
    if visudo -c -f "$SUDOERS_FILE" > /dev/null 2>&1; then
        print_success "Sudoers configuration is valid"
    else
        print_error "Sudoers configuration has errors!"
        rm -f "$SUDOERS_FILE"
        exit 1
    fi
    
    # Setup PHP-FPM logs
    print_info "Setting up PHP-FPM log directories..."
    if [ ! -f "/var/log/php-fpm.log" ]; then
        touch /var/log/php-fpm.log
        chown www-data:www-data /var/log/php-fpm.log
        chmod 644 /var/log/php-fpm.log
    fi
    
    for php_version in $(ls -d /etc/php/*/ 2>/dev/null | grep -oP '\d+\.\d+' | sort -u); do
        log_dir="/var/log/php${php_version}-fpm"
        if [ ! -d "$log_dir" ]; then
            mkdir -p "$log_dir"
            chown www-data:www-data "$log_dir"
            chmod 755 "$log_dir"
        fi
    done
    print_success "PHP-FPM logs configured"
    
    print_success "Phase 2 completed!"
}

#########################################################
# PHASE 3: Application Setup (runs as www-data)
#########################################################
setup_application() {
    print_header "Phase 3: Setting Up Application"
    
    print_info "Application directory: $APP_DIR"
    
    # Set ownership of app directory to www-data
    print_info "Setting ownership to www-data..."
    chown -R www-data:www-data "$APP_DIR"
    
    # Create .env if not exists
    if [ ! -f "$APP_DIR/.env" ]; then
        print_info "Creating .env file..."
        sudo -u www-data cp "$APP_DIR/.env.example" "$APP_DIR/.env"
        print_success ".env file created"
    fi
    
    # Install Composer dependencies (as www-data)
    print_info "Installing Composer dependencies..."
    cd "$APP_DIR"
    sudo -u www-data composer install --no-interaction --prefer-dist --optimize-autoloader --quiet
    print_success "Composer dependencies installed"
    
    # Generate application key
    if ! grep -q "APP_KEY=base64:" "$APP_DIR/.env"; then
        print_info "Generating application key..."
        sudo -u www-data php artisan key:generate --force > /dev/null 2>&1
        print_success "Application key generated"
    fi
    
    # Create required directories (as www-data)
    print_info "Creating required directories..."
    sudo -u www-data mkdir -p "$APP_DIR/storage/app/public"
    sudo -u www-data mkdir -p "$APP_DIR/storage/app/ssh-keys"
    sudo -u www-data mkdir -p "$APP_DIR/storage/framework/cache"
    sudo -u www-data mkdir -p "$APP_DIR/storage/framework/sessions"
    sudo -u www-data mkdir -p "$APP_DIR/storage/framework/views"
    sudo -u www-data mkdir -p "$APP_DIR/storage/logs"
    sudo -u www-data mkdir -p "$APP_DIR/storage/server/nginx"
    sudo -u www-data mkdir -p "$APP_DIR/storage/server/php-fpm"
    sudo -u www-data mkdir -p "$APP_DIR/storage/server/pm2"
    sudo -u www-data mkdir -p "$APP_DIR/bootstrap/cache"
    print_success "Directories created"
    
    # Set permissions
    chmod -R 755 "$APP_DIR/storage"
    chmod -R 755 "$APP_DIR/bootstrap/cache"
    chown -R www-data:www-data "$APP_DIR/storage"
    chown -R www-data:www-data "$APP_DIR/bootstrap/cache"
    print_success "Permissions set"
    
    # Database Setup
    print_header "Database Configuration"
    
    read_input -p "Setup database automatically? (y/n, default: y): " SETUP_DB
    SETUP_DB=${SETUP_DB:-y}
    
    if [[ "$SETUP_DB" =~ ^[Yy]$ ]]; then
        read_input -p "Database name (default: hostiqo): " DB_NAME
        DB_NAME=${DB_NAME:-hostiqo}
        
        read_input -p "Database user (default: webhook_user): " DB_USER
        DB_USER=${DB_USER:-webhook_user}
        
        read_input -sp "Database password: " DB_PASS
        echo ""
        
        if [ -z "$DB_PASS" ]; then
            print_error "Password cannot be empty!"
            exit 1
        fi
        
        # Read MySQL root password
        if [ -f /root/.mysql_root_password ]; then
            MYSQL_ROOT_PASS=$(cat /root/.mysql_root_password)
            print_info "Using MySQL root password from /root/.mysql_root_password"
        else
            read_input -sp "MySQL root password: " MYSQL_ROOT_PASS
            echo ""
        fi
        
        # Create database and user
        print_info "Creating database and user..."
        mysql -u root -p"$MYSQL_ROOT_PASS" << MYSQL_SCRIPT > /dev/null 2>&1
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT CREATE, DROP, ALTER ON *.* TO '$DB_USER'@'localhost';
GRANT CREATE USER ON *.* TO '$DB_USER'@'localhost';
GRANT RELOAD ON *.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`%\`.* TO '$DB_USER'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
MYSQL_SCRIPT
        
        if [ $? -eq 0 ]; then
            print_success "Database '$DB_NAME' and user '$DB_USER' created"
            
            # Update .env
            sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" "$APP_DIR/.env"
            sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" "$APP_DIR/.env"
            sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" "$APP_DIR/.env"
            print_success ".env updated with database credentials"
        else
            print_error "Failed to create database"
        fi
    fi
    
    # Run migrations (as www-data)
    print_info "Running database migrations..."
    if sudo -u www-data php artisan migrate --force > /dev/null 2>&1; then
        print_success "Migrations completed"
        
        # Seed firewall rules
        sudo -u www-data php artisan db:seed --class=FirewallRuleSeeder --force > /dev/null 2>&1 || true
    fi
    
    # Create admin user
    echo ""
    read_input -p "Create admin user? (y/n, default: y): " CREATE_ADMIN
    CREATE_ADMIN=${CREATE_ADMIN:-y}
    
    if [[ "$CREATE_ADMIN" =~ ^[Yy]$ ]]; then
        read_input -p "Admin name (default: Admin): " ADMIN_NAME
        ADMIN_NAME=${ADMIN_NAME:-Admin}
        
        read_input -p "Admin email: " ADMIN_EMAIL
        read_input -sp "Admin password: " ADMIN_PASS
        echo ""
        
        if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASS" ]; then
            sudo -u www-data php artisan tinker --execute="
                \$user = new App\Models\User();
                \$user->name = '$ADMIN_NAME';
                \$user->email = '$ADMIN_EMAIL';
                \$user->password = Hash::make('$ADMIN_PASS');
                \$user->save();
                echo 'User created';
            " > /dev/null 2>&1 && print_success "Admin user created" || print_error "Failed to create admin"
        fi
    fi
    
    # Build frontend assets (as www-data)
    if [ -f "$APP_DIR/package.json" ]; then
        print_info "Installing npm dependencies..."
        cd "$APP_DIR"
        sudo -u www-data npm install --silent > /dev/null 2>&1
        print_info "Building frontend assets..."
        sudo -u www-data npm run build > /dev/null 2>&1
        print_success "Frontend assets built"
    fi
    
    # Create storage link
    sudo -u www-data php artisan storage:link > /dev/null 2>&1
    
    # Optimize
    print_info "Optimizing application..."
    sudo -u www-data php artisan config:cache > /dev/null 2>&1
    sudo -u www-data php artisan route:cache > /dev/null 2>&1
    sudo -u www-data php artisan view:cache > /dev/null 2>&1
    print_success "Application optimized"
    
    # Setup Supervisor configs
    print_info "Creating Supervisor configurations..."
    
    cat > /etc/supervisor/conf.d/hostiqo-queue.conf << EOF
[program:hostiqo-queue]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=$APP_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/queue-worker.log
stopwaitsecs=3600
EOF

    cat > /etc/supervisor/conf.d/hostiqo-scheduler.conf << EOF
[program:hostiqo-scheduler]
process_name=%(program_name)s
command=php $APP_DIR/artisan schedule:work
directory=$APP_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/scheduler.log
EOF

    supervisorctl reread > /dev/null 2>&1
    supervisorctl update > /dev/null 2>&1
    print_success "Supervisor configs installed"
    
    print_success "Phase 3 completed!"
}

#########################################################
# PHASE 4: Web Server Configuration
#########################################################
setup_webserver() {
    print_header "Phase 4: Web Server Configuration"
    
    # Get domain
    read_input -p "Enter your domain name (e.g., hostiqo.example.com): " DOMAIN_NAME
    if [ -z "$DOMAIN_NAME" ]; then
        print_error "Domain name is required!"
        exit 1
    fi
    
    read_input -p "Include www subdomain? (y/n, default: n): " INCLUDE_WWW
    INCLUDE_WWW=${INCLUDE_WWW:-n}
    
    if [[ "$INCLUDE_WWW" =~ ^[Yy]$ ]]; then
        SERVER_NAME="$DOMAIN_NAME www.$DOMAIN_NAME"
        SSL_DOMAINS="-d $DOMAIN_NAME -d www.$DOMAIN_NAME"
    else
        SERVER_NAME="$DOMAIN_NAME"
        SSL_DOMAINS="-d $DOMAIN_NAME"
    fi
    
    read_input -p "Setup SSL certificate? (y/n, default: y): " SETUP_SSL
    SETUP_SSL=${SETUP_SSL:-y}
    
    if [[ "$SETUP_SSL" =~ ^[Yy]$ ]]; then
        read_input -p "Email for SSL notifications: " SSL_EMAIL
        SSL_EMAIL=${SSL_EMAIL:-admin@$DOMAIN_NAME}
    fi
    
    # Detect PHP version
    PHP_VERSION=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+' | head -1)
    PHP_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
    
    # Create Nginx config
    print_info "Creating Nginx configuration..."
    
    cat > /etc/nginx/sites-available/hostiqo << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_NAME;
    root $APP_DIR/public;

    index index.php index.html;
    charset utf-8;

    access_log /var/log/nginx/hostiqo-access.log combined buffer=512k flush=1m;
    error_log /var/log/nginx/hostiqo-error.log warn;

    # Performance - Buffer Tuning
    client_max_body_size 100M;
    client_body_buffer_size 128k;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 16k;
    
    # Timeouts
    client_body_timeout 60s;
    client_header_timeout 60s;
    keepalive_timeout 65s;
    send_timeout 60s;
    
    # Hide server info
    server_tokens off;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 1000;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:$PHP_SOCKET;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # FastCGI Tuning
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
        fastcgi_read_timeout 300s;
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 300s;
    }

    # Block dotfiles
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Block sensitive files
    location ~* \.(env|log|sql|sqlite|bak|backup|old|orig|save|swp|tmp)\$ {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Block access to sensitive directories
    location ~* ^/(\.git|\.svn|\.hg|vendor|node_modules|storage/logs|storage/framework) {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|webp|avif)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    location ~* \.(css|js)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    location ~* \.(svg|woff|woff2|ttf|eot|otf)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Access-Control-Allow-Origin "*";
        access_log off;
    }

    location = /favicon.ico { access_log off; log_not_found off; expires 1y; }
    location = /robots.txt { access_log off; log_not_found off; }
}
EOF

    # Enable site
    ln -sf /etc/nginx/sites-available/hostiqo /etc/nginx/sites-enabled/
    
    # Test and reload Nginx
    if nginx -t > /dev/null 2>&1; then
        systemctl reload nginx
        print_success "Nginx configured and reloaded"
    else
        print_error "Nginx configuration error!"
        nginx -t
        exit 1
    fi
    
    # SSL Setup
    if [[ "$SETUP_SSL" =~ ^[Yy]$ ]]; then
        print_info "Requesting SSL certificate..."
        if certbot --nginx $SSL_DOMAINS --non-interactive --agree-tos --email "$SSL_EMAIL" --redirect > /dev/null 2>&1; then
            print_success "SSL certificate installed"
            
            # Apply SSL hardening
            print_info "Applying SSL hardening..."
            
            # Create SSL params file
            cat > /etc/nginx/snippets/ssl-params.conf << 'SSLEOF'
# SSL Hardening - Modern Configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;

# SSL Session
ssl_session_timeout 1d;
ssl_session_cache shared:SSL:50m;
ssl_session_tickets off;

# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;

# HSTS (2 years)
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
SSLEOF

            # Include SSL params in site config
            if ! grep -q "ssl-params.conf" /etc/nginx/sites-available/hostiqo; then
                sed -i '/listen 443 ssl/a\    include /etc/nginx/snippets/ssl-params.conf;' /etc/nginx/sites-available/hostiqo
            fi
            
            # Reload Nginx with SSL hardening
            if nginx -t > /dev/null 2>&1; then
                systemctl reload nginx
                print_success "SSL hardening applied"
            fi
        else
            print_warning "SSL failed - run manually: sudo certbot --nginx $SSL_DOMAINS"
        fi
    fi
    
    # Start services
    print_info "Starting services..."
    supervisorctl start hostiqo-queue:* > /dev/null 2>&1 || true
    supervisorctl start hostiqo-scheduler:* > /dev/null 2>&1 || true
    print_success "Services started"
    
    print_success "Phase 4 completed!"
}

#########################################################
# CLONE/SETUP REPOSITORY
#########################################################
setup_repository() {
    print_header "Repository Setup"
    
    # Check if already exists
    if [ -d "$DEFAULT_APP_DIR" ] && [ -f "$DEFAULT_APP_DIR/artisan" ]; then
        print_info "Hostiqo already exists at $DEFAULT_APP_DIR"
        read_input -p "Use existing installation? (y/n, default: y): " USE_EXISTING
        USE_EXISTING=${USE_EXISTING:-y}
        
        if [[ "$USE_EXISTING" =~ ^[Yy]$ ]]; then
            APP_DIR="$DEFAULT_APP_DIR"
            print_success "Using existing installation: $APP_DIR"
            return 0
        else
            print_info "Removing existing installation..."
            rm -rf "$DEFAULT_APP_DIR"
        fi
    fi
    
    # Ask for installation path
    read_input -p "Installation path (default: $DEFAULT_APP_DIR): " CUSTOM_PATH
    APP_DIR=${CUSTOM_PATH:-$DEFAULT_APP_DIR}
    
    # Create parent directory
    mkdir -p "$(dirname "$APP_DIR")"
    
    # Clone repository
    print_info "Cloning Hostiqo repository..."
    print_info "Repository: $REPO_URL"
    print_info "Destination: $APP_DIR"
    
    if git clone "$REPO_URL" "$APP_DIR" > /dev/null 2>&1; then
        print_success "Repository cloned successfully"
    else
        print_error "Failed to clone repository"
        print_info "You can clone manually:"
        echo "  git clone $REPO_URL $APP_DIR"
        exit 1
    fi
    
    # Set ownership
    chown -R $WEB_USER:$WEB_USER "$APP_DIR"
    print_success "Ownership set to $WEB_USER"
}

#########################################################
# MAIN EXECUTION
#########################################################
main() {
    print_header "Hostiqo - Server Management Made Simple"
    
    check_root
    
    echo "This installer will:"
    echo "  1. Clone Hostiqo repository to /var/www/hostiqo"
    echo "  2. Install system prerequisites (Nginx, PHP, MySQL, Redis, etc.)"
    echo "  3. Configure sudoers for www-data"
    echo "  4. Setup Laravel application"
    echo "  5. Configure web server and SSL"
    echo ""
    
    read_input -p "Continue with installation? (y/n): " CONTINUE
    if [[ ! "$CONTINUE" =~ ^[Yy]$ ]]; then
        print_info "Installation cancelled"
        exit 0
    fi
    
    # Run all phases
    setup_repository
    install_prerequisites
    configure_sudoers
    setup_application
    setup_webserver
    
    # Final summary
    print_header "Installation Complete! 🎉"
    
    echo ""
    print_success "Hostiqo has been installed successfully!"
    echo ""
    print_info "Installed components:"
    echo "  • Nginx, PHP 7.4-8.4, MySQL, Redis"
    echo "  • Composer, Node.js 20, PM2"
    echo "  • Supervisor, Certbot, fail2ban"
    echo ""
    print_info "Important files:"
    echo "  • MySQL root password: /root/.mysql_root_password"
    echo "  • Nginx config: /etc/nginx/sites-available/hostiqo"
    echo "  • App logs: $APP_DIR/storage/logs/"
    echo ""
    if [[ "$SETUP_SSL" =~ ^[Yy]$ ]]; then
        print_info "Access your panel at: https://$DOMAIN_NAME"
    else
        print_info "Access your panel at: http://$DOMAIN_NAME"
    fi
    echo ""
}

# Parse command line arguments
case "${1:-}" in
    --phase1|--prerequisites)
        check_root
        install_prerequisites
        ;;
    --phase2|--sudoers)
        check_root
        configure_sudoers
        ;;
    --phase3|--app)
        check_root
        APP_DIR="${2:-/var/www/hostiqo}"
        setup_application
        ;;
    --phase4|--webserver)
        check_root
        APP_DIR="${2:-/var/www/hostiqo}"
        setup_webserver
        ;;
    --help|-h)
        echo "Hostiqo Installer"
        echo ""
        echo "Usage: sudo bash install.sh [option]"
        echo ""
        echo "Options:"
        echo "  (no option)      Run full installation"
        echo "  --phase1         Install system prerequisites only"
        echo "  --phase2         Configure sudoers only"
        echo "  --phase3 [path]  Setup Laravel application only"
        echo "  --phase4 [path]  Configure web server only"
        echo "  --help           Show this help"
        echo ""
        ;;
    *)
        main
        ;;
esac
