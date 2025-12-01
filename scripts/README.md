# Setup Scripts for Git Webhook Manager

Automated installation scripts for easy deployment on Ubuntu servers.

## 📋 Scripts Overview

### 1. `setup-ubuntu.sh`
Installs all system prerequisites and dependencies.

**Note:** Installs Node.js 20 system-wide. For multiple Node.js versions, see `setup-nvm-for-user.sh` below.

**Installs:**
- Nginx web server
- PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 with PHP-FPM
- Composer (PHP package manager)
- Node.js 20 with npm (system-wide via NodeSource)
- PM2 (Node.js process manager)
- Redis (queue backend)
- MySQL (database server)
- Supervisor (process manager for Laravel queues)
- Certbot (SSL certificate management)

**Usage:**
```bash
sudo bash scripts/setup-ubuntu.sh
```

**Requirements:** Must be run as root or with sudo
**Time:** ~15-20 minutes depending on internet speed

---

### 2. `setup-sudoers.sh`
Configures passwordless sudo permissions for required commands.

**Configures permissions for:**
- Nginx management (reload, restart, test)
- SSL certificate management (certbot)
- PHP-FPM pool management (all versions)
- Nginx configuration file management
- PM2 process management (Node.js apps)
- Supervisor process management (Laravel queue workers)
- Git deployments

**Usage:**
```bash
# For www-data user (default)
sudo bash scripts/setup-sudoers.sh

# For custom web server user
sudo bash scripts/setup-sudoers.sh your-user
```

**Requirements:** Must be run as root or with sudo
**Time:** < 1 minute

---

### 3. `setup-app.sh`
Sets up the Laravel application and its dependencies.

**Performs:**
- Creates .env file from .env.example
- Installs Composer dependencies
- Generates application key
- Creates required directories
- Sets proper permissions
- Runs database migrations (with prompt)
- Installs npm dependencies
- Builds frontend assets with Vite
- Creates storage symbolic link
- Optimizes application (cache config, routes, views)
- Creates Supervisor configurations for queue workers and scheduler

**Usage:**
```bash
# Run as www-data user (recommended)
sudo -u www-data bash scripts/setup-app.sh

# Or from project directory
bash scripts/setup-app.sh
```

**Requirements:** Must be run from application directory
**Time:** ~5-10 minutes

---

### 4. `setup-nvm-for-user.sh` (Optional)
Installs NVM (Node Version Manager) for a specific user, providing multiple Node.js versions.

**Installs:**
- NVM (Node Version Manager)
- Node.js versions 16, 18, 20, 21
- PM2 for each Node.js version
- Version switching capability

**Usage:**
```bash
# Install for www-data user (default)
sudo bash scripts/setup-nvm-for-user.sh www-data

# Install for custom user
sudo bash scripts/setup-nvm-for-user.sh username
```

**When to use:**
- You need to deploy Node.js apps with specific version requirements
- PM2 manages multiple apps requiring different Node.js versions
- Need to test compatibility across Node.js versions

**When to skip:**
- Only using Laravel with Vite (system Node 20 is sufficient)
- All apps are containerized (Docker manages versions)
- All apps run on Node.js 20

**Requirements:** Run after `setup-ubuntu.sh`
**Time:** ~5-10 minutes

---

## 🚀 Production Deployment - Complete Guide

### Prerequisites
- Fresh Ubuntu 20.04+ server
- Root or sudo access
- Domain name pointing to server (for SSL)
- Basic knowledge of Linux commands

---

### Phase 1: System Setup (as root)

**1. Login to your server**
```bash
ssh root@your-server-ip
```

**2. Update system packages**
```bash
apt update && apt upgrade -y
```

**3. Clone repository to temporary location**
```bash
cd /tmp
git clone https://github.com/hymns/webhook-manager.git setup
cd setup
```

**4. Install system prerequisites**
```bash
# This installs Nginx, PHP, MySQL, Redis, Node.js, Supervisor, etc.
bash scripts/setup-ubuntu.sh
```

**⏱️ Wait ~15-20 minutes for installation to complete**

**5. Configure sudo permissions**
```bash
# This allows www-data to manage services without password
bash scripts/setup-sudoers.sh
```

**6. Secure MySQL installation**
```bash
mysql_secure_installation
```

Follow prompts:
- Set password policy and security options: (skip validation for easier setup)
- Remove anonymous users: Yes
- Disallow root login remotely: Yes
- Remove test database: Yes
- Reload privilege tables: Yes

**7. Create database and user**
```bash
mysql -u root -p
```

```sql
CREATE DATABASE webhook_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'webhook_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON webhook_manager.* TO 'webhook_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### Phase 2: Application Setup

**8. Clone to production location**
```bash
cd /var/www
git clone https://github.com/hymns/webhook-manager.git webhook-manager
```

**9. Set proper ownership**
```bash
chown -R www-data:www-data /var/www/webhook-manager
chmod -R 755 /var/www/webhook-manager
```

**10. Run application setup as www-data user**
```bash
cd /var/www/webhook-manager
sudo -u www-data bash scripts/setup-app.sh
```

**During setup, you'll be prompted:**
- Run migrations now? → Answer **n** (we'll do this after configuring .env)

**⏱️ Wait ~5-10 minutes for npm build to complete**

---

### Phase 3: Configuration

**11. Configure environment file**
```bash
sudo -u www-data nano /var/www/webhook-manager/.env
```

**Update these critical values:**
```env
APP_NAME="Git Webhook Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webhook_manager
DB_USERNAME=webhook_user
DB_PASSWORD=your_secure_password

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**12. Run database migrations**
```bash
cd /var/www/webhook-manager
sudo -u www-data php artisan migrate --force
```

**13. Create admin user**
```bash
sudo -u www-data php artisan tinker
```

```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@your-domain.com';
$user->password = Hash::make('secure_password_here');
$user->save();
exit
```

---

### Phase 4: Web Server Configuration

**14. Create Nginx configuration**
```bash
sudo nano /etc/nginx/sites-available/webhook-manager
```

**Paste this configuration:**
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/webhook-manager/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**15. Enable site and test configuration**
```bash
sudo ln -s /etc/nginx/sites-available/webhook-manager /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

**16. Setup SSL certificate with Certbot**
```bash
# Automatic SSL setup with HTTP to HTTPS redirect
sudo certbot --nginx -d your-domain.com -d www.your-domain.com --non-interactive --agree-tos --email your@email.com --redirect
```

**Or interactive mode (will prompt for email and options):**
```bash
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

The certificate will auto-renew via cron. Check with: `sudo certbot renew --dry-run`

---

### Phase 5: Start Services

**17. Start queue workers and scheduler**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start git-webhook-queue:*
sudo supervisorctl start git-webhook-scheduler:*
```

**18. Verify all services are running**
```bash
sudo supervisorctl status
```

Expected output:
```
git-webhook-queue:git-webhook-queue_00   RUNNING
git-webhook-queue:git-webhook-queue_01   RUNNING
git-webhook-scheduler:git-webhook-scheduler RUNNING
```

---

### ✅ Deployment Complete!

Your application is now live at: `https://your-domain.com`

**Test the installation:**
1. Visit your domain in a browser
2. Login with admin credentials
3. Create a test webhook
4. Check queue workers: `sudo supervisorctl status`
5. Check logs: `tail -f /var/www/webhook-manager/storage/logs/laravel.log`

---

## 🔄 Deploying Updates

When you push new code changes:

```bash
cd /var/www/webhook-manager

# Pull latest changes
sudo -u www-data git pull origin master

# Install/update dependencies (if needed)
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm install

# Build assets
sudo -u www-data npm run build

# Run migrations (if any)
sudo -u www-data php artisan migrate --force

# Clear and optimize caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan cache:clear

# Restart queue workers
sudo supervisorctl restart git-webhook-queue:*

# Reload nginx (if config changed)
sudo systemctl reload nginx
```

**💡 Pro tip:** Create a `deploy.sh` script with these commands!

---

## ⚠️ Important Notes

### Application Location
- ✅ **CORRECT:** `/var/www/webhook-manager`
- ❌ **WRONG:** `/root/webhook-manager` (web server cannot access)

### File Ownership
- ✅ **CORRECT:** `www-data:www-data` (web server user)
- ❌ **WRONG:** `root:root` (permission issues)

### Running Commands
```bash
# ✅ Correct - run as www-data
sudo -u www-data php artisan migrate

# ❌ Wrong - creates root-owned files
php artisan migrate
```

### Setup Scripts Order
1. **First:** `setup-ubuntu.sh` (installs system packages)
2. **Second:** `setup-sudoers.sh` (configures permissions)
3. **Third:** `setup-app.sh` (sets up Laravel application)
4. **Optional:** `setup-nvm-for-user.sh` (for multiple Node.js versions)

### Multiple Node.js Versions (Optional)

The default `setup-ubuntu.sh` installs **Node.js 20 system-wide**, which is sufficient for most use cases including Laravel Vite builds.

**However, if you need multiple Node.js versions** (e.g., for PM2-managed apps requiring specific versions):

```bash
# Install NVM for www-data user
sudo bash scripts/setup-nvm-for-user.sh www-data
```

**This provides:**
- Node.js versions 16, 18, 20, 21
- Version switching: `nvm use 16`, `nvm use 20`, etc.
- Separate PM2 installation per version
- Isolated from system Node.js

**When to use NVM:**
- ✅ You deploy Node.js apps via webhooks that require specific versions
- ✅ PM2 manages apps with different Node.js version requirements
- ✅ Need to test apps across multiple Node.js versions

**When NOT to use NVM:**
- ✅ Only using Laravel with Vite (system Node 20 is enough)
- ✅ Apps are containerized with Docker (version managed per container)
- ✅ All deployed apps can run on Node.js 20

**Using NVM after installation:**
```bash
# Switch to www-data user
sudo -u www-data -i

# List available versions
nvm list

# Use specific version
nvm use 16
node -v  # v16.x.x

# Start PM2 app with Node 16
pm2 start app.js --name "app-node16"

# Switch to Node 20
nvm use 20
node -v  # v20.x.x

# Start PM2 app with Node 20
pm2 start app.js --name "app-node20"

# Exit www-data shell
exit
```

**Note:** System-wide Node.js (from `setup-ubuntu.sh`) and NVM can coexist:
- System Node 20: Used by default for `sudo -u www-data npm run build`
- NVM versions: Available when you login as www-data with `sudo -u www-data -i`

---

## 🔧 Manual Installation

If you prefer manual installation or need to customize, refer to [PREREQUISITES.md](../PREREQUISITES.md) for detailed instructions.

---

## 🐛 Troubleshooting

### Script fails during execution
```bash
# Check the error message
# Most common issues:
# 1. Not running with sudo (for system scripts)
# 2. Internet connection issues
# 3. Package repository issues

# Fix package repository issues:
sudo apt-get update --fix-missing
```

### Permissions errors
```bash
# Verify web server user
ps aux | grep nginx

# Re-run sudoers script with correct user
sudo bash scripts/setup-sudoers.sh www-data
```

### Database connection errors
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u root -p

# Update .env file with correct credentials
```

### Queue worker not starting
```bash
# Check Supervisor status
sudo supervisorctl status

# View logs
tail -f storage/logs/queue-worker.log

# Restart manually
sudo supervisorctl restart git-webhook-queue:*
```

---

## 📝 Post-Installation

### Create Admin User
```bash
php artisan tinker
>>> $user = new App\Models\User();
>>> $user->name = 'Admin';
>>> $user->email = 'admin@example.com';
>>> $user->password = bcrypt('password');
>>> $user->save();
```

### Test SSL Certificate Request
```bash
# Dry run (test without actually requesting)
sudo certbot --nginx -d your-domain.com --dry-run
```

### Verify Services
```bash
# Check Nginx
sudo systemctl status nginx

# Check PHP-FPM (all versions)
sudo systemctl status php*-fpm

# Check Redis
sudo systemctl status redis

# Check MySQL
sudo systemctl status mysql
```

---

## ⚙️ Configuration Files

All configuration files are created in:

- **Nginx:** `/etc/nginx/sites-available/` and `/etc/nginx/sites-enabled/`
- **PHP-FPM Pools:** `/etc/php/*/fpm/pool.d/`
- **PM2 Configs:** `/etc/pm2/`
- **Supervisor:** `/etc/supervisor/conf.d/`
- **Sudoers:** `/etc/sudoers.d/git-webhook-manager`

---

## 🔒 Security Notes

### Sudoers File
The sudoers configuration file is created with restricted permissions (0440) and only allows specific commands needed for the application to function.

### File Permissions
All created files and directories follow the principle of least privilege:
- Web files: 755 (directories) / 644 (files)
- Sensitive configs: 440 (sudoers)
- Private keys: 600

### Review Permissions
After installation, review the sudoers file:
```bash
sudo cat /etc/sudoers.d/git-webhook-manager
```

---

## 📚 Additional Resources

- [Main README](../README.md) - Application documentation
- [PREREQUISITES.md](../PREREQUISITES.md) - Detailed system requirements
- [Laravel Documentation](https://laravel.com/docs) - Laravel framework docs

---

## 💡 Tips

1. **Take snapshots** before running scripts on production servers
2. **Test on staging** environment first
3. **Review logs** after installation: `/var/log/` and `storage/logs/`
4. **Keep backups** of your configurations
5. **Update regularly** with `sudo apt-get update && sudo apt-get upgrade`

---

## 🆘 Need Help?

If you encounter issues:
1. Check the error messages carefully
2. Review the logs
3. Verify your system meets the requirements
4. Consult the troubleshooting section above
5. Check Laravel logs: `storage/logs/laravel.log`
