#!/bin/bash

#########################################################
# Webhook Manager - Application Setup Script
# Sets up Laravel application and services
#########################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

print_header() {
    echo -e "${BLUE}=========================================="
    echo -e "$1"
    echo -e "==========================================${NC}"
}

# Get application directory
APP_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

print_header "Webhook Manager - Application Setup"
echo ""
print_info "Application directory: $APP_DIR"
echo ""

# Check if .env exists
if [ ! -f "$APP_DIR/.env" ]; then
    print_info "Creating .env file from example..."
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    print_success ".env file created"
else
    print_info ".env file already exists"
fi

# Install Composer dependencies
print_info "Installing Composer dependencies..."
cd "$APP_DIR"
composer install --no-interaction --prefer-dist --optimize-autoloader --quiet
print_success "Composer dependencies installed"

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" "$APP_DIR/.env"; then
    print_info "Generating application key..."
    php artisan key:generate --force > /dev/null 2>&1
    print_success "Application key generated"
else
    print_info "Application key already set"
fi

# Create required directories
print_info "Creating required directories..."
mkdir -p "$APP_DIR/storage/app/public"
mkdir -p "$APP_DIR/storage/app/ssh-keys"
mkdir -p "$APP_DIR/storage/framework/cache"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/storage/server/nginx"
mkdir -p "$APP_DIR/storage/server/php-fpm"
mkdir -p "$APP_DIR/storage/server/pm2"
mkdir -p "$APP_DIR/bootstrap/cache"
print_success "Directories created"

# Set permissions
print_info "Setting directory permissions..."
chmod -R 755 "$APP_DIR/storage"
chmod -R 755 "$APP_DIR/bootstrap/cache"
print_success "Permissions set"

# Database Setup
print_header "Database Configuration"
echo ""
read -p "Setup database automatically? (y/n, default: y): " SETUP_DB
SETUP_DB=${SETUP_DB:-y}

if [[ "$SETUP_DB" =~ ^[Yy]$ ]]; then
    print_info "Database setup - please provide details:"
    echo ""
    
    # Get database details
    read -p "Database name (default: webhook_manager): " DB_NAME
    DB_NAME=${DB_NAME:-webhook_manager}
    
    read -p "Database user (default: webhook_user): " DB_USER
    DB_USER=${DB_USER:-webhook_user}
    
    read -sp "Database password: " DB_PASS
    echo ""
    
    if [ -z "$DB_PASS" ]; then
        print_error "Password cannot be empty!"
        exit 1
    fi
    
    read -p "MySQL root password: " -s MYSQL_ROOT_PASS
    echo ""
    
    if [ -z "$MYSQL_ROOT_PASS" ]; then
        print_error "MySQL root password cannot be empty!"
        exit 1
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
        
        # Update .env file
        print_info "Updating .env file with database credentials..."
        
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" "$APP_DIR/.env"
            sed -i '' "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" "$APP_DIR/.env"
            sed -i '' "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" "$APP_DIR/.env"
        else
            # Linux
            sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" "$APP_DIR/.env"
            sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" "$APP_DIR/.env"
            sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" "$APP_DIR/.env"
        fi
        
        print_success ".env file updated with database credentials"
        
        # Test database connection
        print_info "Testing database connection..."
        if php artisan db:show > /dev/null 2>&1; then
            print_success "Database connection successful!"
        else
            print_error "Database connection failed - please check credentials"
        fi
    else
        print_error "Failed to create database - please check MySQL root password"
        print_info "You can create database manually and update .env file"
    fi
else
    print_info "Skipped database setup - configure manually in .env file"
fi

echo ""

# Run database migrations
print_header "Database Migrations"
echo ""

# Check if database is configured
if grep -q "DB_DATABASE=webhook_manager" "$APP_DIR/.env"; then
    read -p "Run migrations now? (y/n, default: y): " RUN_MIGRATIONS
    RUN_MIGRATIONS=${RUN_MIGRATIONS:-y}
    
    if [[ "$RUN_MIGRATIONS" =~ ^[Yy]$ ]]; then
        print_info "Running database migrations..."
        if php artisan migrate --force > /dev/null 2>&1; then
            print_success "Migrations completed"
            
            # Create admin user
            echo ""
            read -p "Create admin user now? (y/n, default: y): " CREATE_ADMIN
            CREATE_ADMIN=${CREATE_ADMIN:-y}
            
            if [[ "$CREATE_ADMIN" =~ ^[Yy]$ ]]; then
                print_info "Admin user creation:"
                echo ""
                
                read -p "Admin name (default: Admin): " ADMIN_NAME
                ADMIN_NAME=${ADMIN_NAME:-Admin}
                
                read -p "Admin email: " ADMIN_EMAIL
                if [ -z "$ADMIN_EMAIL" ]; then
                    print_error "Email cannot be empty!"
                else
                    read -sp "Admin password: " ADMIN_PASS
                    echo ""
                    
                    if [ -z "$ADMIN_PASS" ]; then
                        print_error "Password cannot be empty!"
                    else
                        print_info "Creating admin user..."
                        
                        php artisan tinker << TINKER_SCRIPT > /dev/null 2>&1
\$user = new App\Models\User();
\$user->name = '$ADMIN_NAME';
\$user->email = '$ADMIN_EMAIL';
\$user->password = Hash::make('$ADMIN_PASS');
\$user->save();
exit
TINKER_SCRIPT
                        
                        if [ $? -eq 0 ]; then
                            print_success "Admin user created successfully!"
                            echo ""
                            print_info "Admin credentials:"
                            echo "  Email: $ADMIN_EMAIL"
                            echo "  Password: [hidden]"
                        else
                            print_error "Failed to create admin user"
                            print_info "Create manually: php artisan tinker"
                        fi
                    fi
                fi
            else
                print_info "Skipped admin creation - create manually later"
            fi
        else
            print_error "Migrations failed"
        fi
    else
        print_info "Skipped migrations - run manually with: php artisan migrate"
    fi
else
    print_info "Database not configured in .env - skipping migrations"
fi

echo ""

# Build frontend assets
if [ -f "$APP_DIR/package.json" ]; then
    print_info "Installing npm dependencies..."
    npm install --silent > /dev/null 2>&1
    print_success "NPM dependencies installed"
    
    print_info "Building frontend assets..."
    npm run build > /dev/null 2>&1
    print_success "Frontend assets built"
fi

# Create symbolic link for storage
print_info "Creating storage symbolic link..."
php artisan storage:link > /dev/null 2>&1
print_success "Storage link created"

# Clear and cache config
print_info "Optimizing application..."
php artisan config:cache > /dev/null 2>&1
php artisan route:cache > /dev/null 2>&1
php artisan view:cache > /dev/null 2>&1
print_success "Application optimized"

# Setup Supervisor configs
print_header "Supervisor Configuration"
echo ""
print_info "Creating Supervisor configurations..."

# Queue worker config
cat > /tmp/webhook-manager-queue.conf << 'EOF'
[program:webhook-manager-queue]
process_name=%(program_name)s_%(process_num)02d
command=php APPDIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=APPDIR/storage/logs/queue-worker.log
stopwaitsecs=3600
EOF

# Scheduler config
cat > /tmp/webhook-manager-scheduler.conf << 'EOF'
[program:webhook-manager-scheduler]
process_name=%(program_name)s
command=php APPDIR/artisan schedule:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=APPDIR/storage/logs/scheduler.log
EOF

# Replace APPDIR placeholder (cross-platform compatible)
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s|APPDIR|$APP_DIR|g" /tmp/webhook-manager-queue.conf
    sed -i '' "s|APPDIR|$APP_DIR|g" /tmp/webhook-manager-scheduler.conf
else
    # Linux
    sed -i "s|APPDIR|$APP_DIR|g" /tmp/webhook-manager-queue.conf
    sed -i "s|APPDIR|$APP_DIR|g" /tmp/webhook-manager-scheduler.conf
fi

# Install supervisor configs if supervisor is installed
if command -v supervisorctl &> /dev/null; then
    print_info "Installing Supervisor configs..."
    sudo cp /tmp/webhook-manager-queue.conf /etc/supervisor/conf.d/ > /dev/null 2>&1
    sudo cp /tmp/webhook-manager-scheduler.conf /etc/supervisor/conf.d/ > /dev/null 2>&1
    sudo supervisorctl reread > /dev/null 2>&1
    sudo supervisorctl update > /dev/null 2>&1
    sudo supervisorctl start webhook-manager-queue:* > /dev/null 2>&1
    sudo supervisorctl start webhook-manager-scheduler:* > /dev/null 2>&1
    print_success "Supervisor configs installed and started"
else
    print_info "Supervisor not installed - configs saved to /tmp/"
    print_info "Install supervisor: sudo apt-get install supervisor"
    print_info "Then copy configs manually from /tmp/"
fi

# Summary
print_header "Setup Complete!"
echo ""
print_success "Application setup completed successfully!"
echo ""
print_info "What's been configured:"
echo "  ✓ Composer dependencies installed"
echo "  ✓ Application key generated"
echo "  ✓ Directory structure created"
echo "  ✓ Permissions set correctly"
echo "  ✓ Database migrations ready"
echo "  ✓ Frontend assets built"
echo "  ✓ Application optimized"
echo "  ✓ PHP-FPM log files and directories"
echo "  ✓ Supervisor configs created"
echo ""
print_info "Next steps:"
echo "  1. Configure your .env file (database, mail, etc.)"
echo "  2. Run: php artisan migrate (if not done already)"
echo "  3. Create admin user"
echo "  4. Configure your web server (Nginx/Apache)"
echo ""
print_info "Development server:"
echo "  php artisan serve"
echo ""
print_info "Queue worker (dev):"
echo "  php artisan queue:work"
echo ""
print_info "Scheduler (dev):"
echo "  php artisan schedule:work"
echo ""
