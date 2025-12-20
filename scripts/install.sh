#!/bin/bash

#########################################################
# Hostiqo - Complete Installer
# Server Management Made Simple
#
# Author: Muhammad Hamizi Jaminan
# Website: https://hostiqo.dev
# License: MIT
#
# Run with: sudo bash scripts/install.sh
#########################################################

# Note: We don't use 'set -e' to allow graceful error handling
# Each critical command should handle its own errors

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

# Enable fail2ban jail
ensure_jail() {
    local order="$1"   # 00, 10, 20, 21
    local jail="$2"    # sshd, nginx-botsearch, etc
    local extra="$3"   # optional extra config

    local dir="/etc/fail2ban/jail.d"
    local file="${dir}/${order}-${jail}.local"

    mkdir -p "$dir"

    # Check fail2ban installed
    command -v fail2ban-client >/dev/null 2>&1 || return 0

    # Check jail exists
    fail2ban-client status 2>/dev/null | grep -q "$jail" || return 0

    # Idempotent: do nothing if file already exists
    [ -f "$file" ] && return 0

    cat > "$file" <<EOF
[$jail]
enabled = true
${extra}
EOF
}

# Default installation path
DEFAULT_APP_DIR="/var/www/hostiqo"
REPO_URL="https://github.com/hymns/hostiqo.git"

# Will be set after clone/detection
APP_DIR=""

# OS Detection variables (set by detect_os)
OS_FAMILY=""      # debian or rhel
OS_ID=""          # ubuntu, debian, rocky, alma, centos, rhel
OS_VERSION=""     # e.g., 22.04, 9, 8
PKG_MANAGER=""    # apt or dnf/yum
WEB_USER=""       # www-data or nginx
PHP_FPM_SERVICE="" # php8.x-fpm or php-fpm
SYSTEMCTL="/bin/systemctl"

#########################################################
# OS DETECTION
#########################################################
detect_os() {
    print_info "Detecting operating system..."

    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_ID="$ID"
        OS_VERSION="$VERSION_ID"
    else
        print_error "Cannot detect OS. /etc/os-release not found."
        exit 1
    fi

    case "$OS_ID" in
        ubuntu|debian)
            OS_FAMILY="debian"
            PKG_MANAGER="apt"
            WEB_USER="www-data"
            ;;
        rocky|almalinux|centos|rhel)
            OS_FAMILY="rhel"
            WEB_USER="nginx"
            # Use dnf if available, fallback to yum
            if command -v dnf &> /dev/null; then
                PKG_MANAGER="dnf"
            else
                PKG_MANAGER="yum"
            fi
            ;;
        *)
            print_error "Unsupported OS: $OS_ID"
            print_info "Supported: Ubuntu, Debian, Rocky Linux, AlmaLinux, CentOS, RHEL"
            exit 1
            ;;
    esac

    print_success "Detected: $OS_ID $OS_VERSION ($OS_FAMILY family)"
    print_info "Package manager: $PKG_MANAGER"
    print_info "Web user: $WEB_USER"
}

#########################################################
# PHASE 1: System Prerequisites
#########################################################
install_prerequisites() {
    print_header "Phase 1: Installing System Prerequisites"
    
    if [ "$OS_FAMILY" = "debian" ]; then
        install_prerequisites_debian
    else
        install_prerequisites_rhel
    fi

    # Common post-installation tasks
    install_common_tools
    configure_security
    
    # Set ownership of app directory (now that web user exists)
    if [ -d "$APP_DIR" ]; then
        print_info "Setting ownership of $APP_DIR to $WEB_USER..."
        chown -R $WEB_USER:$WEB_USER "$APP_DIR"
        print_success "Ownership set to $WEB_USER"
    fi

    print_success "Phase 1 completed!"
}

#########################################################
# DEBIAN/UBUNTU Prerequisites
#########################################################
install_prerequisites_debian() {
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
    
    # Install Node.js
    print_info "Adding NodeSource repository for Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    print_info "Installing Node.js 20..."
    apt-get install -y nodejs > /dev/null 2>&1
    print_success "Node.js $(node -v) installed"
    
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
    
    # Install fail2ban
    print_info "Installing fail2ban..."
    apt-get install -y fail2ban > /dev/null 2>&1
    print_success "fail2ban installed"

    # Create web directories
    print_info "Creating web directories..."
    mkdir -p /var/www
    chown -R www-data:www-data /var/www
    chmod -R 755 /var/www
    print_success "Web directories created"
}

#########################################################
# RHEL/Rocky/Alma/CentOS Prerequisites
#########################################################
install_prerequisites_rhel() {
    # Update system
    print_info "Updating system packages..."
    $PKG_MANAGER update -y > /dev/null 2>&1
    print_success "System updated"

    # Install EPEL repository
    print_info "Installing EPEL repository..."
    $PKG_MANAGER install -y epel-release > /dev/null 2>&1
    print_success "EPEL repository installed"

    # Install basic dependencies (openssl MUST be first for secure_database)
    print_info "Installing basic dependencies..."
    $PKG_MANAGER install -y openssl > /dev/null 2>&1 || true
    $PKG_MANAGER install -y ca-certificates curl wget git net-tools unzip \
        gcc gcc-c++ make gnupg2 openssl-devel > /dev/null 2>&1
    print_success "Basic dependencies installed"

    # Install Nginx
    print_info "Installing Nginx..."
    $PKG_MANAGER install -y nginx > /dev/null 2>&1
    systemctl enable nginx > /dev/null 2>&1
    systemctl start nginx > /dev/null 2>&1
    print_success "Nginx installed and started"

    # Add Remi repository for PHP
    print_info "Adding Remi repository for PHP..."
    if [ "$OS_ID" = "centos" ] && [ "${OS_VERSION%%.*}" = "7" ]; then
        $PKG_MANAGER install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm > /dev/null 2>&1
    else
        # Rocky 8/9, Alma 8/9, RHEL 8/9, CentOS Stream
        $PKG_MANAGER install -y https://rpms.remirepo.net/enterprise/remi-release-${OS_VERSION%%.*}.rpm > /dev/null 2>&1
    fi
    print_success "Remi repository added"

    # Enable Remi PHP module
    print_info "Enabling PHP module..."
    if command -v dnf &> /dev/null; then
        dnf module reset php -y > /dev/null 2>&1 || true
    fi

    # Install multiple PHP versions
    print_info "Installing PHP versions (7.4, 8.0, 8.1, 8.2, 8.3, 8.4)..."
    for version in 74 80 81 82 83 84; do
        version_dot="${version:0:1}.${version:1}"
        print_info "Installing PHP $version_dot..."
        $PKG_MANAGER install -y \
            php${version}-php-fpm \
            php${version}-php-cli \
            php${version}-php-common \
            php${version}-php-mysqlnd \
            php${version}-php-pgsql \
            php${version}-php-pdo \
            php${version}-php-zip \
            php${version}-php-gd \
            php${version}-php-mbstring \
            php${version}-php-curl \
            php${version}-php-xml \
            php${version}-php-bcmath \
            php${version}-php-intl \
            php${version}-php-redis \
            php${version}-php-opcache > /dev/null 2>&1

        # Configure PHP-FPM to use nginx user for socket
        FPM_CONF="/etc/opt/remi/php${version}/php-fpm.d/www.conf"
        if [ -f "$FPM_CONF" ]; then
            sed -i 's/^user = .*/user = nginx/' "$FPM_CONF"
            sed -i 's/^group = .*/group = nginx/' "$FPM_CONF"
            sed -i 's/^;listen.owner = .*/listen.owner = nginx/' "$FPM_CONF"
            sed -i 's/^;listen.group = .*/listen.group = nginx/' "$FPM_CONF"
            sed -i 's/^listen.owner = .*/listen.owner = nginx/' "$FPM_CONF"
            sed -i 's/^listen.group = .*/listen.group = nginx/' "$FPM_CONF"
            # Disable ACL users (overrides owner/group settings)
            sed -i 's/^listen.acl_users = .*/;listen.acl_users = /' "$FPM_CONF"
        fi

        # Enable and start PHP-FPM service
        systemctl enable php${version}-php-fpm > /dev/null 2>&1
        systemctl start php${version}-php-fpm > /dev/null 2>&1
        print_success "PHP $version_dot installed"
    done

    # Create symlink for default PHP
    if [ ! -f /usr/bin/php ]; then
        ln -sf /opt/remi/php84/root/usr/bin/php /usr/bin/php
    fi

    # Install Node.js
    print_info "Adding NodeSource repository for Node.js 20..."
    curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    print_info "Installing Node.js 20..."
    $PKG_MANAGER install -y nodejs > /dev/null 2>&1
    print_success "Node.js $(node -v) installed"

    # Install Redis
    print_info "Installing Redis..."
    if command -v dnf &> /dev/null; then
        dnf module enable redis:7 -y > /dev/null 2>&1 || true
    fi
    $PKG_MANAGER install -y redis > /dev/null 2>&1 || {
        print_warning "Redis package not found, trying redis6..."
        $PKG_MANAGER install -y redis6 > /dev/null 2>&1 || true
    }
    systemctl enable redis > /dev/null 2>&1 || true
    systemctl start redis > /dev/null 2>&1 || true
    print_success "Redis installed and started"

    # Install MySQL/MariaDB
    print_info "Installing MariaDB..."
    $PKG_MANAGER install -y mariadb-server mariadb > /dev/null 2>&1 || {
        print_error "Failed to install MariaDB"
        exit 1
    }
    systemctl enable mariadb > /dev/null 2>&1 || true
    systemctl start mariadb > /dev/null 2>&1 || true
    print_success "MariaDB installed and started"

    # Install Certbot
    print_info "Installing Certbot..."
    $PKG_MANAGER install -y certbot python3-certbot-nginx > /dev/null 2>&1 || {
        print_warning "certbot-nginx not found, trying certbot only..."
        $PKG_MANAGER install -y certbot > /dev/null 2>&1 || true
    }
    print_success "Certbot installed"

    # Install Supervisor
    print_info "Installing Supervisor..."
    $PKG_MANAGER install -y supervisor > /dev/null 2>&1 || {
        print_error "Failed to install Supervisor"
        exit 1
    }
    systemctl enable supervisord > /dev/null 2>&1 || true
    systemctl start supervisord > /dev/null 2>&1 || true
    print_success "Supervisor installed and started"

    # Install fail2ban
    print_info "Installing fail2ban..."
    $PKG_MANAGER install -y fail2ban > /dev/null 2>&1
    print_success "fail2ban installed"

    # Create web directories
    print_info "Creating web directories..."
    mkdir -p /var/www
    chown -R nginx:nginx /var/www
    chmod -R 755 /var/www
    print_success "Web directories created"

    # Configure SELinux for web
    print_info "Configuring SELinux..."
    if command -v getenforce &> /dev/null; then
        # Set SELinux to permissive mode for Hostiqo compatibility
        # httpd_t context has issues with sudo/PAM when enforcing
        sed -i 's/SELINUX=enforcing/SELINUX=permissive/' /etc/selinux/config 2>/dev/null || true
        setenforce 0 2>/dev/null || true
        print_success "SELinux set to permissive mode"
    fi
}

#########################################################
# Common Tools Installation
#########################################################
install_common_tools() {
    # Install Composer (install to /usr/bin for better sudo compatibility)
    print_info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php > /dev/null 2>&1
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    # Create symlink in /usr/bin for sudo access
    ln -sf /usr/local/bin/composer /usr/bin/composer 2>/dev/null || true
    print_success "Composer installed"

    # Install PM2
    print_info "Installing PM2..."
    npm install -g pm2 > /dev/null 2>&1
    pm2 startup systemd > /dev/null 2>&1 || true
    print_success "PM2 installed"
    
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
}

#########################################################
# Security Configuration
#########################################################
configure_security() {
    print_header "Security Hardening"
    
    # Configure fail2ban
    print_info "Configuring fail2ban defaults..."
    if [ -f /etc/fail2ban/jail.conf ]; then
        ensure_jail 00 DEFAULT "
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd
ignoreip = 127.0.0.1/8 ::1
"
        ensure_jail 10 sshd
        ensure_jail 20 nginx-botsearch
        ensure_jail 21 nginx-http-auth
    fi
    systemctl enable fail2ban > /dev/null 2>&1
    systemctl restart fail2ban > /dev/null 2>&1
    print_success "fail2ban configured and enabled"

    # Configure firewall based on OS
    if [ "$OS_FAMILY" = "debian" ]; then
        configure_ufw
    else
        configure_firewalld
    fi
    
    # Secure MySQL/MariaDB
    secure_database
}

#########################################################
# UFW Firewall (Debian/Ubuntu)
#########################################################
configure_ufw() {
    print_info "Configuring UFW firewall..."
    if command -v ufw &> /dev/null; then
        ufw --force enable > /dev/null 2>&1
        ufw default deny incoming > /dev/null 2>&1
        ufw default allow outgoing > /dev/null 2>&1
        ufw allow ssh > /dev/null 2>&1
        ufw allow 'Nginx Full' > /dev/null 2>&1
        print_success "UFW firewall configured"
    fi
}

#########################################################
# Firewalld (RHEL/Rocky/Alma/CentOS)
#########################################################
configure_firewalld() {
    print_info "Configuring firewalld..."
    if command -v firewall-cmd &> /dev/null; then
        systemctl enable firewalld > /dev/null 2>&1
        systemctl start firewalld > /dev/null 2>&1
        firewall-cmd --permanent --add-service=http > /dev/null 2>&1
        firewall-cmd --permanent --add-service=https > /dev/null 2>&1
        firewall-cmd --permanent --add-service=ssh > /dev/null 2>&1
        firewall-cmd --reload > /dev/null 2>&1
        print_success "firewalld configured"
    fi
}

#########################################################
# Secure Database
#########################################################
secure_database() {
    print_info "Securing database installation..."
    MYSQL_ROOT_PASS=$(openssl rand -base64 32)

    if [ "$OS_FAMILY" = "debian" ]; then
        mysql --user=root <<_EOF_ > /dev/null 2>&1 || true
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASS}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';
FLUSH PRIVILEGES;
_EOF_
    else
        # MariaDB on RHEL-based systems uses unix_socket by default
        # We need to set password and switch to mysql_native_password
        mysql --user=root <<_EOF_ > /dev/null 2>&1 || true
-- Update root authentication to use password
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('${MYSQL_ROOT_PASS}');
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';
FLUSH PRIVILEGES;
_EOF_
        
        # If ALTER USER fails, try alternative method for older MariaDB
        if ! mysql --user=root -p"${MYSQL_ROOT_PASS}" -e "SELECT 1" > /dev/null 2>&1; then
            mysql --user=root <<_EOF_ > /dev/null 2>&1 || true
UPDATE mysql.user SET Password=PASSWORD('${MYSQL_ROOT_PASS}'), plugin='mysql_native_password' WHERE User='root' AND Host='localhost';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';
FLUSH PRIVILEGES;
_EOF_
        fi
    fi
    
    echo "$MYSQL_ROOT_PASS" > /root/.mysql_root_password
    chmod 600 /root/.mysql_root_password
    print_success "Database secured (root password: /root/.mysql_root_password)"
}

#########################################################
# PHASE 2: Sudoers Configuration
#########################################################

# Debian/Ubuntu sudoers configuration
configure_sudoers_debian() {
    cat > "$SUDOERS_FILE" << 'DEBIAN_EOF'
# Hostiqo - Automated Management Permissions (Debian/Ubuntu)
# Web server user: www-data

# Nginx Management
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx

# Certbot - SSL Certificate Management
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot
www-data ALL=(ALL) NOPASSWD: /snap/bin/certbot

# PHP-FPM Pool Management
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start php*-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop php*-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php*-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart php*-fpm
www-data ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm* -t
www-data ALL=(ALL) NOPASSWD: /usr/sbin/php-fpm* -t *

# File Management - PHP-FPM Pool Config Files
www-data ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf
www-data ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf
www-data ALL=(ALL) NOPASSWD: /bin/rm -f /etc/php/[78].[0-9]*/fpm/pool.d/[a-zA-Z0-9._-]*.conf

# File Management - Nginx Config Files
www-data ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/nginx/sites-available/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/nginx/sites-available/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/ln -sf /etc/nginx/sites-available/[a-zA-Z0-9._-]* /etc/nginx/sites-enabled/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/rm -f /etc/nginx/sites-available/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/rm -f /etc/nginx/sites-enabled/[a-zA-Z0-9._-]*

# Webroot Directory Management
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/[a-zA-Z0-9._-]*/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/chown -R [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/www/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/chown [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/www/[a-zA-Z0-9._-]*/*
www-data ALL=(ALL) NOPASSWD: /bin/chmod -R [0-9]* /var/www/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/chmod [0-9]* /var/www/[a-zA-Z0-9._-]*/*
www-data ALL=(ALL) NOPASSWD: /bin/mv /tmp/* /var/www/[a-zA-Z0-9._-]*/*
www-data ALL=(ALL) NOPASSWD: /bin/rm -rf /var/www/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /usr/bin/find /var/www/[a-zA-Z0-9._-]* *

# Nginx Cache Directory
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/cache/nginx/*
www-data ALL=(ALL) NOPASSWD: /bin/chown -R [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/cache/nginx/*
www-data ALL=(ALL) NOPASSWD: /bin/chmod -R [0-9]* /var/cache/nginx/*

# PHP-FPM Log Directory
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/php*-fpm
www-data ALL=(ALL) NOPASSWD: /bin/chown [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/log/php*-fpm

# PM2 Process Control
www-data ALL=(ALL) NOPASSWD: /usr/bin/pm2
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/pm2
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /etc/pm2
www-data ALL=(ALL) NOPASSWD: /bin/cp /tmp/[a-zA-Z0-9._-]* /etc/pm2/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/pm2/[a-zA-Z0-9._-]*
www-data ALL=(ALL) NOPASSWD: /bin/rm -f /etc/pm2/[a-zA-Z0-9._-]*

# Supervisor - Process Manager
www-data ALL=(ALL) NOPASSWD: /bin/cp /tmp/hostiqo-*.conf /etc/supervisor/conf.d/*.conf
www-data ALL=(ALL) NOPASSWD: /usr/bin/cp /tmp/hostiqo-*.conf /etc/supervisor/conf.d/*.conf
www-data ALL=(ALL) NOPASSWD: /bin/rm -f /etc/supervisor/conf.d/*.conf
www-data ALL=(ALL) NOPASSWD: /bin/chmod 644 /etc/supervisor/conf.d/*.conf
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl reread
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl update
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl start *
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl stop *
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart *
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl status
www-data ALL=(ALL) NOPASSWD: /usr/bin/tail -n * /var/log/supervisor/*.log

# Service Management
www-data ALL=(ALL) NOPASSWD: /bin/systemctl status *
www-data ALL=(ALL) NOPASSWD: /bin/systemctl is-active *
www-data ALL=(ALL) NOPASSWD: /bin/systemctl is-enabled *
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start supervisor
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop supervisor
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart supervisor
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload supervisor
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start redis-server
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop redis-server
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart redis-server
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start mysql
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop mysql
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart mysql
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start mariadb
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop mariadb
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart mariadb
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start fail2ban
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop fail2ban
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart fail2ban
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload fail2ban
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start ufw
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop ufw
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart ufw
www-data ALL=(ALL) NOPASSWD: /bin/systemctl enable ufw
www-data ALL=(ALL) NOPASSWD: /bin/systemctl disable ufw

# Journal logs
www-data ALL=(ALL) NOPASSWD: /usr/bin/journalctl -u * -n * --no-pager

# Git
www-data ALL=(ALL) NOPASSWD: /usr/bin/git

# UFW Firewall
www-data ALL=(ALL) NOPASSWD: /usr/sbin/ufw
www-data ALL=(ALL) NOPASSWD: /usr/sbin/ufw *

# Crontab
www-data ALL=(ALL) NOPASSWD: /usr/bin/crontab
www-data ALL=(ALL) NOPASSWD: /usr/bin/crontab *

# Log File Access
www-data ALL=(ALL) NOPASSWD: /usr/bin/tail -n * /var/log/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/tail /var/log/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/cat /var/log/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/truncate -s 0 *
DEBIAN_EOF
}

# RHEL/Rocky/Alma sudoers configuration
configure_sudoers_rhel() {
    cat > "$SUDOERS_FILE" << 'RHEL_EOF'
# Hostiqo - Automated Management Permissions (RHEL/Rocky/Alma)
# Web server user: nginx

# Nginx Management
nginx ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl start nginx
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop nginx
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx

# Certbot - SSL Certificate Management
nginx ALL=(ALL) NOPASSWD: /usr/bin/certbot

# PHP-FPM Pool Management (Remi style)
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl start php*-php-fpm
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop php*-php-fpm
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload php*-php-fpm
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php*-php-fpm

# File Management - PHP-FPM Pool Config Files (Remi)
nginx ALL=(ALL) NOPASSWD: /usr/bin/cp /tmp/[a-zA-Z0-9._-]* /etc/opt/remi/php*/php-fpm.d/[a-zA-Z0-9._-]*.conf
nginx ALL=(ALL) NOPASSWD: /usr/bin/chmod 644 /etc/opt/remi/php*/php-fpm.d/[a-zA-Z0-9._-]*.conf
nginx ALL=(ALL) NOPASSWD: /usr/bin/rm -f /etc/opt/remi/php*/php-fpm.d/[a-zA-Z0-9._-]*.conf

# File Management - Nginx Config Files
nginx ALL=(ALL) NOPASSWD: /usr/bin/cp /tmp/[a-zA-Z0-9._-]* /etc/nginx/conf.d/[a-zA-Z0-9._-]*.conf
nginx ALL=(ALL) NOPASSWD: /usr/bin/chmod 644 /etc/nginx/conf.d/[a-zA-Z0-9._-]*.conf
nginx ALL=(ALL) NOPASSWD: /usr/bin/rm -f /etc/nginx/conf.d/[a-zA-Z0-9._-]*.conf

# Webroot Directory Management
nginx ALL=(ALL) NOPASSWD: /usr/bin/mkdir -p /var/www/[a-zA-Z0-9._-]*
nginx ALL=(ALL) NOPASSWD: /usr/bin/mkdir -p /var/www/[a-zA-Z0-9._-]*/[a-zA-Z0-9._-]*
nginx ALL=(ALL) NOPASSWD: /usr/bin/chown -R [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/www/[a-zA-Z0-9._-]*
nginx ALL=(ALL) NOPASSWD: /usr/bin/chown [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/www/[a-zA-Z0-9._-]*/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/chmod -R [0-9]* /var/www/[a-zA-Z0-9._-]*
nginx ALL=(ALL) NOPASSWD: /usr/bin/chmod [0-9]* /var/www/[a-zA-Z0-9._-]*/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/mv /tmp/* /var/www/[a-zA-Z0-9._-]*/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/rm -rf /var/www/[a-zA-Z0-9._-]*
nginx ALL=(ALL) NOPASSWD: /usr/bin/find /var/www/[a-zA-Z0-9._-]* *

# Nginx Cache Directory
nginx ALL=(ALL) NOPASSWD: /usr/bin/mkdir -p /var/cache/nginx/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/chown -R [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/cache/nginx/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/chmod -R [0-9]* /var/cache/nginx/*

# PHP-FPM Log Directory
nginx ALL=(ALL) NOPASSWD: /usr/bin/mkdir -p /var/log/php*-fpm
nginx ALL=(ALL) NOPASSWD: /usr/bin/chown [a-zA-Z0-9_-]*?[a-zA-Z0-9_-]* /var/log/php*-fpm

# PM2 Process Control
nginx ALL=(ALL) NOPASSWD: /usr/bin/pm2
nginx ALL=(ALL) NOPASSWD: /usr/local/bin/pm2
nginx ALL=(ALL) NOPASSWD: /usr/bin/mkdir -p /etc/pm2
nginx ALL=(ALL) NOPASSWD: /usr/bin/cp /tmp/[a-zA-Z0-9._-]* /etc/pm2/[a-zA-Z0-9._-]*
nginx ALL=(ALL) NOPASSWD: /usr/bin/chmod 644 /etc/pm2/[a-zA-Z0-9._-]*
nginx ALL=(ALL) NOPASSWD: /usr/bin/rm -f /etc/pm2/[a-zA-Z0-9._-]*

# Supervisor - Process Manager
nginx ALL=(ALL) NOPASSWD: /usr/bin/cp /tmp/hostiqo-*.ini /etc/supervisord.d/*.ini
nginx ALL=(ALL) NOPASSWD: /usr/bin/rm -f /etc/supervisord.d/*.ini
nginx ALL=(ALL) NOPASSWD: /usr/bin/chmod 644 /etc/supervisord.d/*.ini
nginx ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl reread
nginx ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl update
nginx ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl start *
nginx ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl stop *
nginx ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart *
nginx ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl status
nginx ALL=(ALL) NOPASSWD: /usr/bin/tail -n * /var/log/supervisor/*.log

# Service Management
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl status *
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl is-active *
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl is-enabled *
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl start supervisord
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop supervisord
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart supervisord
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload supervisord
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl start redis
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop redis
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart redis
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl start mariadb
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop mariadb
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart mariadb
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl start fail2ban
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop fail2ban
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart fail2ban
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload fail2ban

# Firewalld
nginx ALL=(ALL) NOPASSWD: /usr/bin/firewall-cmd
nginx ALL=(ALL) NOPASSWD: /usr/bin/firewall-cmd *
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl start firewalld
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop firewalld
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl enable firewalld
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl disable firewalld
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart firewalld
nginx ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload firewalld

# Journal logs
nginx ALL=(ALL) NOPASSWD: /usr/bin/journalctl -u * -n * --no-pager

# Git
nginx ALL=(ALL) NOPASSWD: /usr/bin/git

# Crontab
nginx ALL=(ALL) NOPASSWD: /usr/bin/crontab
nginx ALL=(ALL) NOPASSWD: /usr/bin/crontab *

# Log File Access
nginx ALL=(ALL) NOPASSWD: /usr/bin/tail -n * /var/log/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/tail /var/log/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/cat /var/log/*
nginx ALL=(ALL) NOPASSWD: /usr/bin/truncate -s 0 *

# SELinux
nginx ALL=(ALL) NOPASSWD: /usr/sbin/semanage fcontext *
nginx ALL=(ALL) NOPASSWD: /usr/sbin/restorecon *
nginx ALL=(ALL) NOPASSWD: /usr/sbin/setsebool *
RHEL_EOF
}

configure_sudoers() {
    print_header "Phase 2: Configuring Sudoers"
    
    SUDOERS_FILE="/etc/sudoers.d/hostiqo-manager"
    
    print_info "Creating sudoers configuration for $WEB_USER ($OS_FAMILY)..."

    if [ "$OS_FAMILY" = "rhel" ]; then
        configure_sudoers_rhel
    else
        configure_sudoers_debian
    fi

    chmod 0440 "$SUDOERS_FILE"
    
    # Validate sudoers
    if visudo -c -f "$SUDOERS_FILE" > /dev/null 2>&1; then
        print_success "Sudoers configuration is valid"
    else
        print_error "Sudoers configuration has errors!"
        rm -f "$SUDOERS_FILE"
        exit 1
    fi
    
    # Setup PHP-FPM logs based on OS
    print_info "Setting up PHP-FPM log directories..."
    if [ ! -f "/var/log/php-fpm.log" ]; then
        touch /var/log/php-fpm.log
        chown $WEB_USER:$WEB_USER /var/log/php-fpm.log
        chmod 644 /var/log/php-fpm.log
    fi
    
    if [ "$OS_FAMILY" = "debian" ]; then
        for php_version in $(ls -d /etc/php/*/ 2>/dev/null | grep -oP '\d+\.\d+' | sort -u); do
            log_dir="/var/log/php${php_version}-fpm"
            if [ ! -d "$log_dir" ]; then
                mkdir -p "$log_dir"
                chown $WEB_USER:$WEB_USER "$log_dir"
                chmod 755 "$log_dir"
            fi
        done
    else
        # RHEL-based: Remi PHP logs are in /var/opt/remi/phpXX/log/php-fpm/
        for php_dir in /var/opt/remi/php*/log/php-fpm; do
            if [ -d "$php_dir" ]; then
                chown -R $WEB_USER:$WEB_USER "$php_dir" 2>/dev/null || true
            fi
        done
    fi
    print_success "PHP-FPM logs configured"
    
    print_success "Phase 2 completed!"
}

#########################################################
# PHASE 3: Application Setup
#########################################################
setup_application() {
    print_header "Phase 3: Setting Up Application"
    
    print_info "Application directory: $APP_DIR"
    
    # Set ownership of app directory
    print_info "Setting ownership to $WEB_USER..."
    chown -R $WEB_USER:$WEB_USER "$APP_DIR"
    
    # Create .env if not exists
    if [ ! -f "$APP_DIR/.env" ]; then
        print_info "Creating .env file..."
        sudo -u $WEB_USER cp "$APP_DIR/.env.example" "$APP_DIR/.env"
        print_success ".env file created"
    fi
    
    # Install Composer dependencies
    print_info "Installing Composer dependencies..."
    cd "$APP_DIR"
    sudo -u $WEB_USER composer install --no-interaction --prefer-dist --optimize-autoloader --quiet
    print_success "Composer dependencies installed"
    
    # Generate application key
    if ! grep -q "APP_KEY=base64:" "$APP_DIR/.env"; then
        print_info "Generating application key..."
        sudo -u $WEB_USER php artisan key:generate --force > /dev/null 2>&1
        print_success "Application key generated"
    fi
    
    # Create required directories
    print_info "Creating required directories..."
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/app/public"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/app/ssh-keys"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/framework/cache"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/framework/sessions"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/framework/views"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/logs"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/server/nginx"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/server/php-fpm"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/storage/server/pm2"
    sudo -u $WEB_USER mkdir -p "$APP_DIR/bootstrap/cache"
    print_success "Directories created"
    
    # Set permissions
    chmod -R 755 "$APP_DIR/storage"
    chmod -R 755 "$APP_DIR/bootstrap/cache"
    chown -R $WEB_USER:$WEB_USER "$APP_DIR/storage"
    chown -R $WEB_USER:$WEB_USER "$APP_DIR/bootstrap/cache"
    print_success "Permissions set"
    
    # Database Setup
    print_header "Database Configuration"
    
    read_input -p "Setup database automatically? (y/n, default: y): " SETUP_DB
    SETUP_DB=${SETUP_DB:-y}
    
    if [[ "$SETUP_DB" =~ ^[Yy]$ ]]; then
        read_input -p "Database name (default: hostiqo_db): " DB_NAME
        DB_NAME=${DB_NAME:-hostiqo_db}
        
        read_input -p "Database user (default: hostiqo_user): " DB_USER
        DB_USER=${DB_USER:-hostiqo_user}
        
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
    
    # Run migrations
    print_info "Running database migrations..."
    if sudo -u $WEB_USER php artisan migrate --force > /dev/null 2>&1; then
        print_success "Migrations completed"
        
        # Seed firewall rules
        sudo -u $WEB_USER php artisan db:seed --class=FirewallRuleSeeder --force > /dev/null 2>&1 || true
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
            sudo -u $WEB_USER php artisan tinker --execute="
                \$user = new App\Models\User();
                \$user->name = '$ADMIN_NAME';
                \$user->email = '$ADMIN_EMAIL';
                \$user->password = Hash::make('$ADMIN_PASS');
                \$user->save();
                echo 'User created';
            " > /dev/null 2>&1 && print_success "Admin user created" || print_error "Failed to create admin"
        fi
    fi
    
    # Build frontend assets
    if [ -f "$APP_DIR/package.json" ]; then
        print_info "Installing npm dependencies..."
        cd "$APP_DIR"
        sudo -u $WEB_USER npm install --silent > /dev/null 2>&1
        print_info "Building frontend assets..."
        sudo -u $WEB_USER npm run build > /dev/null 2>&1
        print_success "Frontend assets built"
    fi
    
    # Create storage link
    sudo -u $WEB_USER php artisan storage:link > /dev/null 2>&1
    
    # Optimize
    print_info "Optimizing application..."
    sudo -u $WEB_USER php artisan config:cache > /dev/null 2>&1
    sudo -u $WEB_USER php artisan route:cache > /dev/null 2>&1
    sudo -u $WEB_USER php artisan view:cache > /dev/null 2>&1
    print_success "Application optimized"
    
    # Setup Supervisor configs based on OS
    print_info "Creating Supervisor configurations..."
    
    if [ "$OS_FAMILY" = "debian" ]; then
        # Debian/Ubuntu: /etc/supervisor/conf.d/*.conf
        SUPERVISOR_DIR="/etc/supervisor/conf.d"
        SUPERVISOR_EXT="conf"
    else
        # RHEL-based: /etc/supervisord.d/*.ini
        SUPERVISOR_DIR="/etc/supervisord.d"
        SUPERVISOR_EXT="ini"
    fi

    mkdir -p "$SUPERVISOR_DIR"

    cat > "$SUPERVISOR_DIR/hostiqo-queue.$SUPERVISOR_EXT" << EOF
[program:hostiqo-queue]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=$APP_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$WEB_USER
numprocs=2
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/queue-worker.log
stopwaitsecs=3600
EOF

    cat > "$SUPERVISOR_DIR/hostiqo-scheduler.$SUPERVISOR_EXT" << EOF
[program:hostiqo-scheduler]
process_name=%(program_name)s
command=php $APP_DIR/artisan schedule:work
directory=$APP_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$WEB_USER
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
    
    # Detect PHP version and socket path based on OS
    PHP_VERSION=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+' | head -1)

    if [ "$OS_FAMILY" = "debian" ]; then
        PHP_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
        NGINX_CONF_DIR="/etc/nginx/sites-available"
        NGINX_ENABLED_DIR="/etc/nginx/sites-enabled"
    else
        # RHEL-based: Remi PHP uses different socket path
        PHP_VERSION_NODOT=$(echo $PHP_VERSION | tr -d '.')
        PHP_SOCKET="/var/opt/remi/php${PHP_VERSION_NODOT}/run/php-fpm/www.sock"
        NGINX_CONF_DIR="/etc/nginx/conf.d"
        NGINX_ENABLED_DIR=""  # RHEL doesn't use sites-enabled
    fi
    
    # Create Nginx config
    print_info "Creating Nginx configuration..."
    
    # Determine config file path
    if [ "$OS_FAMILY" = "debian" ]; then
        NGINX_CONF_FILE="$NGINX_CONF_DIR/hostiqo"
    else
        NGINX_CONF_FILE="$NGINX_CONF_DIR/hostiqo.conf"
    fi

    cat > "$NGINX_CONF_FILE" << EOF
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

    # Enable site (Debian uses symlinks, RHEL uses conf.d directly)
    if [ "$OS_FAMILY" = "debian" ]; then
        ln -sf "$NGINX_CONF_FILE" "$NGINX_ENABLED_DIR/hostiqo"
    fi
    
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
            
            # Create SSL params file (create snippets dir if needed)
            mkdir -p /etc/nginx/snippets
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
            if ! grep -q "ssl-params.conf" "$NGINX_CONF_FILE"; then
                sed -i '/listen 443 ssl/a\    include /etc/nginx/snippets/ssl-params.conf;' "$NGINX_CONF_FILE"
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
    
    # Configure SELinux context for web directory on RHEL
    if [ "$OS_FAMILY" = "rhel" ] && command -v semanage &> /dev/null; then
        print_info "Setting SELinux context for web directory..."
        semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/storage(/.*)?" > /dev/null 2>&1 || true
        semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/bootstrap/cache(/.*)?" > /dev/null 2>&1 || true
        restorecon -Rv "$APP_DIR" > /dev/null 2>&1 || true
        print_success "SELinux context configured"
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
    
    # Note: Ownership will be set after prerequisites install (when web user exists)
    print_info "Ownership will be set after prerequisites installation"
}

#########################################################
# MAIN EXECUTION
#########################################################
main() {
    print_header "Hostiqo - Server Management Made Simple"
    
    check_root
    detect_os
    
    echo ""
    echo "This installer will:"
    echo "  1. Clone Hostiqo repository to /var/www/hostiqo"
    echo "  2. Install system prerequisites (Nginx, PHP, MySQL/MariaDB, Redis, etc.)"
    echo "  3. Configure sudoers for $WEB_USER"
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
    print_info "Detected OS: $OS_ID $OS_VERSION ($OS_FAMILY)"
    print_info "Web user: $WEB_USER"
    echo ""
    print_info "Installed components:"
    if [ "$OS_FAMILY" = "debian" ]; then
        echo "  • Nginx, PHP 7.4-8.4, MySQL, Redis"
    else
        echo "  • Nginx, PHP 7.4-8.4, MariaDB, Redis"
    fi
    echo "  • Composer, Node.js 20, PM2"
    echo "  • Supervisor, Certbot, fail2ban"
    echo ""
    print_info "Important files:"
    echo "  • Database root password: /root/.mysql_root_password"
    if [ "$OS_FAMILY" = "debian" ]; then
        echo "  • Nginx config: /etc/nginx/sites-available/hostiqo"
    else
        echo "  • Nginx config: /etc/nginx/conf.d/hostiqo.conf"
    fi
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
        detect_os
        install_prerequisites
        ;;
    --phase2|--sudoers)
        check_root
        detect_os
        configure_sudoers
        ;;
    --phase3|--app)
        check_root
        detect_os
        APP_DIR="${2:-/var/www/hostiqo}"
        setup_application
        ;;
    --phase4|--webserver)
        check_root
        detect_os
        APP_DIR="${2:-/var/www/hostiqo}"
        setup_webserver
        ;;
    --help|-h)
        echo "Hostiqo Installer"
        echo ""
        echo "Usage: sudo bash install.sh [option]"
        echo ""
        echo "Supported OS:"
        echo "  - Ubuntu / Debian"
        echo "  - Rocky Linux / AlmaLinux / CentOS / RHEL"
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
