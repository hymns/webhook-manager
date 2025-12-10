<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class NginxService
{
    protected string $nginxSitesAvailable;
    protected string $nginxSitesEnabled;
    protected string $nginxConfigTest;
    protected string $nginxReload;
    protected bool $isLocal;

    public function __construct()
    {
        $this->isLocal = in_array(config('app.env'), ['local', 'dev', 'development']);
        
        if ($this->isLocal) {
            // Use storage directory for local/dev environments
            $storageRoot = storage_path('server');
            $this->nginxSitesAvailable = "{$storageRoot}/nginx/sites-available";
            $this->nginxSitesEnabled = "{$storageRoot}/nginx/sites-enabled";
            $this->nginxConfigTest = 'echo "[LOCAL] Nginx config test (skipped)"';
            $this->nginxReload = 'echo "[LOCAL] Nginx reload (skipped)"';
            
            // Create directories if they don't exist
            $this->ensureLocalDirectories();
        } else {
            // Production paths
            $this->nginxSitesAvailable = '/etc/nginx/sites-available';
            $this->nginxSitesEnabled = '/etc/nginx/sites-enabled';
            $this->nginxConfigTest = 'sudo /usr/sbin/nginx -t';
            $this->nginxReload = 'sudo /bin/systemctl reload nginx';
        }
    }

    /**
     * Ensure local storage directories exist
     */
    protected function ensureLocalDirectories(): void
    {
        $dirs = [
            storage_path('server/nginx/sites-available'),
            storage_path('server/nginx/sites-enabled'),
            storage_path('server/logs/nginx'),
        ];

        foreach ($dirs as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
    }

    /**
     * Generate Nginx configuration for a website
     */
    public function generateConfig(Website $website): string
    {
        if ($website->project_type === 'php') {
            return $this->generatePhpConfig($website);
        } else {
            return $this->generateNodeConfig($website);
        }
    }

    /**
     * Generate PHP project Nginx configuration
     */
    protected function generatePhpConfig(Website $website): string
    {
        // Append working_directory to root_path
        $workingDir = $website->working_directory ?? '/';
        $workingDir = trim($workingDir, '/'); // Remove leading/trailing slashes
        
        // Environment-aware document root
        if ($this->isLocal) {
            // Local mode: Use storage/server/www/{domain}/
            $baseRoot = storage_path("server/www/{$website->domain}");
            $documentRoot = $baseRoot . ($workingDir ? '/' . $workingDir : '');
        } else {
            // Production mode: Use actual root_path
            $documentRoot = rtrim($website->root_path, '/') . ($workingDir ? '/' . $workingDir : '');
        }
        
        $sslConfig = $website->ssl_enabled ? $this->getSslConfig($website->domain) : '';
        $securityHeaders = $this->getSecurityHeaders();
        
        // Use custom PHP-FPM pool socket if available
        $poolName = $website->php_pool_name ?? str_replace('.', '_', $website->domain);
        
        // Environment-aware paths
        if ($this->isLocal) {
            $socketPath = $website->php_pool_name 
                ? storage_path("server/php/php{$website->php_version}-fpm-{$poolName}.sock")
                : storage_path("server/php/php{$website->php_version}-fpm.sock");
            $logDir = storage_path('server/logs/nginx');
        } else {
            $socketPath = $website->php_pool_name 
                ? "/var/run/php/php{$website->php_version}-fpm-{$poolName}.sock"
                : "/var/run/php/php{$website->php_version}-fpm.sock";
            $logDir = '/var/log/nginx';
        }

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$website->domain};

{$sslConfig}

    root {$documentRoot};
    index index.php index.html index.htm;

    # Logging
    access_log {$logDir}/{$website->domain}-access.log;
    error_log {$logDir}/{$website->domain}-error.log;

    # Security: Limit request body size
    client_max_body_size 100M;
    client_body_buffer_size 128k;

    # Security: Timeouts
    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 15;
    send_timeout 10;

{$securityHeaders}

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{$socketPath};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_read_timeout 300;
    }

    # Security: Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Security: Deny access to sensitive files
    location ~* \.(env|log|md|sql|sqlite|conf|ini|bak|old|tmp|swp)$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Security: Deny access to common exploit files
    location ~* (\.(git|svn|hg|bzr)|composer\.(json|lock)|package(-lock)?\.json|Dockerfile|nginx\.conf)$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Optimize: Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
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
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_min_length 1000;
    gzip_proxied any;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript image/svg+xml;
    gzip_disable "MSIE [1-6]\.";
}
NGINX;
    }

    /**
     * Generate Node.js project Nginx configuration
     */
    protected function generateNodeConfig(Website $website): string
    {
        $port = $website->port ?? 3000;
        $sslConfig = $website->ssl_enabled ? $this->getSslConfig($website->domain) : '';
        $securityHeaders = $this->getSecurityHeaders();
        
        // Environment-aware log paths
        $logDir = $this->isLocal ? storage_path('server/logs/nginx') : '/var/log/nginx';

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$website->domain};

{$sslConfig}

    # Logging
    access_log {$logDir}/{$website->domain}-access.log;
    error_log {$logDir}/{$website->domain}-error.log;

    # Security: Limit request body size
    client_max_body_size 100M;
    client_body_buffer_size 128k;

    # Security: Timeouts
    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 15;
    send_timeout 10;

{$securityHeaders}

    # Proxy to Node.js application
    location / {
        proxy_pass http://localhost:{$port};
        proxy_http_version 1.1;
        
        # WebSocket support
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        
        # Proxy headers
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Host \$host;
        proxy_set_header X-Forwarded-Port \$server_port;
        
        # Proxy timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
        
        # Proxy buffering
        proxy_buffering on;
        proxy_buffer_size 4k;
        proxy_buffers 8 4k;
        proxy_busy_buffers_size 8k;
        
        # Cache bypass for WebSocket
        proxy_cache_bypass \$http_upgrade;
    }

    # Security: Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Optimize: Static file caching (if served by Nginx)
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files \$uri @proxy;
    }

    # Fallback to proxy for assets not found
    location @proxy {
        proxy_pass http://localhost:{$port};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
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
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_min_length 1000;
    gzip_proxied any;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript image/svg+xml;
    gzip_disable "MSIE [1-6]\.";
}
NGINX;
    }

    /**
     * Get SSL configuration snippet with hardening
     */
    protected function getSslConfig(string $domain): string
    {
        return <<<SSL
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    
    # SSL Certificates
    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    ssl_trusted_certificate /etc/letsencrypt/live/{$domain}/chain.pem;
    
    # SSL Protocols (TLS 1.2 and 1.3 only)
    ssl_protocols TLSv1.2 TLSv1.3;
    
    # SSL Ciphers (Strong ciphers only)
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;
    
    # SSL Session
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;
    
    # OCSP Stapling (improves SSL handshake performance)
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 1.1.1.1 valid=300s;
    resolver_timeout 5s;
    
    # Security: HSTS (HTTP Strict Transport Security)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    # Redirect HTTP to HTTPS
    if (\$scheme != "https") {
        return 301 https://\$host\$request_uri;
    }
SSL;
    }

    /**
     * Get security headers configuration
     */
    protected function getSecurityHeaders(): string
    {
        return <<<HEADERS
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    
    # Hide Nginx version
    server_tokens off;
HEADERS;
    }

    /**
     * Write Nginx configuration file
     */
    public function writeConfig(Website $website): array
    {
        try {
            $config = $this->generateConfig($website);
            $filename = $this->getConfigFilename($website);
            $filepath = "{$this->nginxSitesAvailable}/{$filename}";

            if ($this->isLocal) {
                // Local mode: Direct file write
                File::put($filepath, $config);
                
                // Create webroot directory in storage/server/www/ for debugging
                $workingDir = $website->working_directory ?? '/';
                $workingDir = trim($workingDir, '/');
                $baseRoot = storage_path("server/www/{$website->domain}");
                $documentRoot = $baseRoot . ($workingDir ? '/' . $workingDir : '');
                
                if (!File::exists($documentRoot)) {
                    File::makeDirectory($documentRoot, 0755, true);
                    
                    // Create sample index file for testing
                    $indexFile = $documentRoot . '/index.html';
                    if (!File::exists($indexFile)) {
                        $sampleContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$website->name}</title>
</head>
<body>
    <h1>Welcome to {$website->name}</h1>
    <p>Domain: {$website->domain}</p>
    <p>Project Type: {$website->project_type}</p>
    <p>Root Path (Production): {$website->root_path}</p>
    <p>Working Directory: {$website->working_directory}</p>
    <p>This is a sample file created in LOCAL mode for debugging.</p>
    <p><strong>In production, this will point to: {$website->root_path}</strong></p>
    <hr>
    <small>Generated by Git Webhook Manager</small>
</body>
</html>
HTML;
                        File::put($indexFile, $sampleContent);
                    }
                }
                
                Log::info('[LOCAL] Nginx config and webroot created', [
                    'filepath' => $filepath,
                    'webroot' => $documentRoot,
                    'production_path' => $website->root_path,
                    'website_id' => $website->id
                ]);
            } else {
                // Production mode: Use sudo
                $tempFile = tempnam(sys_get_temp_dir(), 'nginx_');
                File::put($tempFile, $config);

                // Move to nginx directory with sudo
                $result = Process::run("sudo cp {$tempFile} {$filepath}");
                
                // Clean up temp file
                @unlink($tempFile);
                
                if ($result->failed()) {
                    throw new \Exception("Failed to write config file: " . $result->errorOutput());
                }

                // Set proper permissions
                Process::run("sudo chmod 644 {$filepath}");
                
                // Create webroot directory in production
                $workingDir = $website->working_directory ?? '/';
                $workingDir = trim($workingDir, '/');
                $documentRoot = rtrim($website->root_path, '/') . ($workingDir ? '/' . $workingDir : '');
                
                if (!File::exists($documentRoot)) {
                    // Create directory with sudo
                    $mkdirResult = Process::run("sudo mkdir -p {$documentRoot}");
                    
                    if ($mkdirResult->successful()) {
                        // Set ownership to www-data for root path (parent directory)
                        $rootPath = rtrim($website->root_path, '/');
                        Process::run("sudo chown -R www-data:www-data {$rootPath}");
                        Process::run("sudo chmod -R 755 {$rootPath}");

                        // Create sample index file for testing
                        $indexFile = $documentRoot . '/index.html';
                        if (!File::exists($indexFile)) {
                            $sampleContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$website->name}</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #2563eb; }
        .info { background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .info p { margin: 5px 0; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 4px; }
        .success { color: #059669; font-weight: 600; }
    </style>
</head>
<body>
    <h1>✅ Welcome to {$website->name}</h1>
    <p class="success">Your website is successfully configured!</p>

    <div class="info">
        <p><strong>Domain:</strong> {$website->domain}</p>
        <p><strong>Project Type:</strong> {$website->project_type}</p>
        <p><strong>Root Path:</strong> <code>{$website->root_path}</code></p>
        <p><strong>Working Directory:</strong> <code>{$website->working_directory}</code></p>
        <p><strong>Document Root:</strong> <code>{$documentRoot}</code></p>
    </div>

    <h3>📝 Next Steps:</h3>
    <ol>
        <li>Deploy your application files to: <code>{$website->root_path}</code></li>
        <li>Set up your Git webhook for automatic deployments</li>
        <li>This placeholder file will be replaced by your actual application</li>
    </ol>

    <hr>
    <small>Generated by Git Webhook Manager • Environment: Production</small>
</body>
</html>
HTML;
                            // Create temp file and move with sudo to preserve permissions
                            $tempFile = sys_get_temp_dir() . '/index_' . uniqid() . '.html';
                            File::put($tempFile, $sampleContent);
                            Process::run("sudo mv {$tempFile} {$indexFile}");
                            Process::run("sudo chown www-data:www-data {$indexFile}");
                            Process::run("sudo chmod 644 {$indexFile}");

                            Log::info('[PRODUCTION] Sample index.html created', [
                                'file' => $indexFile,
                                'website_id' => $website->id
                            ]);
                        }

                        Log::info('[PRODUCTION] Webroot directory created', [
                            'webroot' => $documentRoot,
                            'website_id' => $website->id
                        ]);
                    }
                }
            }

            return [
                'success' => true,
                'filepath' => $filepath,
                'message' => 'Config file created successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to write Nginx config', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enable Nginx site (create symlink)
     */
    public function enableSite(Website $website): array
    {
        try {
            $filename = $this->getConfigFilename($website);
            $source = "{$this->nginxSitesAvailable}/{$filename}";
            $target = "{$this->nginxSitesEnabled}/{$filename}";

            if ($this->isLocal) {
                // Local mode: Direct symlink
                if (File::exists($target)) {
                    File::delete($target);
                }
                symlink($source, $target);
                
                Log::info('[LOCAL] Nginx site enabled', [
                    'source' => $source,
                    'target' => $target
                ]);
            } else {
                // Production mode: Use sudo
                $result = Process::run("sudo ln -sf {$source} {$target}");
                
                if ($result->failed()) {
                    throw new \Exception("Failed to create symlink: " . $result->errorOutput());
                }
            }

            return [
                'success' => true,
                'message' => 'Site enabled successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to enable Nginx site', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Disable Nginx site (remove symlink)
     */
    public function disableSite(Website $website): array
    {
        try {
            $filename = $this->getConfigFilename($website);
            $target = "{$this->nginxSitesEnabled}/{$filename}";

            if ($this->isLocal) {
                // Local mode: Direct delete
                if (File::exists($target)) {
                    File::delete($target);
                }
                
                Log::info('[LOCAL] Nginx site disabled', ['target' => $target]);
            } else {
                // Production mode: Use sudo
                $result = Process::run("sudo rm -f {$target}");
                
                if ($result->failed()) {
                    throw new \Exception("Failed to remove symlink: " . $result->errorOutput());
                }
            }

            return [
                'success' => true,
                'message' => 'Site disabled successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to disable Nginx site', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Nginx configuration
     */
    public function testConfig(): array
    {
        $result = Process::run($this->nginxConfigTest);

        // Nginx -t outputs to stderr, so capture both stdout and stderr
        $output = trim($result->output() . "\n" . $result->errorOutput());

        return [
            'success' => $result->successful(),
            'output' => $output
        ];
    }

    /**
     * Reload Nginx
     */
    public function reload(): array
    {
        $result = Process::run($this->nginxReload);

        return [
            'success' => $result->successful(),
            'output' => $result->output()
        ];
    }

    /**
     * Delete Nginx configuration
     */
    public function deleteConfig(Website $website): array
    {
        try {
            $filename = $this->getConfigFilename($website);
            
            // Disable site first
            $this->disableSite($website);
            
            // Remove config file
            $filepath = "{$this->nginxSitesAvailable}/{$filename}";
            
            if ($this->isLocal) {
                // Local mode: Direct delete
                if (File::exists($filepath)) {
                    File::delete($filepath);
                }
                
                Log::info('[LOCAL] Nginx config deleted', ['filepath' => $filepath]);
            } else {
                // Production mode: Use sudo
                $result = Process::run("sudo rm -f {$filepath}");
                
                if ($result->failed()) {
                    throw new \Exception("Failed to delete config file: " . $result->errorOutput());
                }
            }

            return [
                'success' => true,
                'message' => 'Config deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete Nginx config', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get config filename
     */
    protected function getConfigFilename(Website $website): string
    {
        return $website->domain;
    }

    /**
     * Request SSL certificate using certbot
     */
    public function requestSslCertificate(Website $website): array
    {
        try {
            $command = sprintf(
                'sudo certbot --nginx -d %s --non-interactive --agree-tos --email %s',
                escapeshellarg($website->domain),
                escapeshellarg(config('mail.from.address', 'admin@example.com'))
            );

            $result = Process::run($command);

            if ($result->failed()) {
                throw new \Exception("Certbot failed: " . $result->errorOutput());
            }

            return [
                'success' => true,
                'message' => 'SSL certificate installed successfully',
                'output' => $result->output()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to request SSL certificate', [
                'website_id' => $website->id,
                'domain' => $website->domain,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Deploy full Nginx config (write, test, enable, reload)
     */
    public function deploy(Website $website): array
    {
        // Write config
        $writeResult = $this->writeConfig($website);
        if (!$writeResult['success']) {
            return $writeResult;
        }

        // Test config
        $testResult = $this->testConfig();
        if (!$testResult['success']) {
            return [
                'success' => false,
                'error' => 'Nginx config test failed: ' . $testResult['output']
            ];
        }

        // Enable site
        $enableResult = $this->enableSite($website);
        if (!$enableResult['success']) {
            return $enableResult;
        }

        // Reload Nginx
        $reloadResult = $this->reload();
        if (!$reloadResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to reload Nginx: ' . $reloadResult['output']
            ];
        }

        return [
            'success' => true,
            'message' => 'Nginx configuration deployed successfully'
        ];
    }

    /**
     * Generate WordPress-optimized Nginx configuration
     */
    public function generateWordPressConfig(Website $website, bool $enableCache = true): string
    {
        $domain = $website->domain;
        $rootPath = $website->root_path;
        $phpVersion = $website->php_version ?? '8.3';
        $poolName = $website->php_pool_name ?? $this->generatePoolName($website);
        $socketPath = "/var/run/php/php{$phpVersion}-fpm-{$poolName}.sock";
        $cacheDir = "/var/cache/nginx/wordpress-{$domain}";

        $cacheConfig = '';
        $cacheBypass = '';
        $fastcgiCache = '';

        if ($enableCache) {
            $cacheConfig = <<<CACHE

    # FastCGI Cache Configuration
    fastcgi_cache_path {$cacheDir} levels=1:2 keys_zone=WORDPRESS_{$domain}:100m inactive=60m max_size=1g use_temp_path=off;
    fastcgi_cache_key "\$scheme\$request_method\$host\$request_uri";
    fastcgi_cache_use_stale error timeout updating http_500 http_503;
    fastcgi_cache_background_update on;
    fastcgi_cache_lock on;
CACHE;

            $cacheBypass = <<<BYPASS

    # Cache bypass conditions
    set \$skip_cache 0;
    
    # POST requests and urls with a query string should always go to PHP
    if (\$request_method = POST) {
        set \$skip_cache 1;
    }
    if (\$query_string != "") {
        set \$skip_cache 1;
    }
    
    # Don't cache uris containing the following segments
    if (\$request_uri ~* "/wp-admin/|/xmlrpc.php|wp-.*.php|/feed/|index.php|sitemap(_index)?.xml") {
        set \$skip_cache 1;
    }
    
    # Don't use the cache for logged in users or recent commenters
    if (\$http_cookie ~* "comment_author|wordpress_[a-f0-9]+|wp-postpass|wordpress_logged_in") {
        set \$skip_cache 1;
    }
BYPASS;

            $fastcgiCache = <<<FCACHE

        # FastCGI cache settings
        fastcgi_cache WORDPRESS_{$domain};
        fastcgi_cache_valid 200 60m;
        fastcgi_cache_valid 404 10m;
        fastcgi_cache_bypass \$skip_cache;
        fastcgi_no_cache \$skip_cache;
        add_header X-FastCGI-Cache \$upstream_cache_status;
FCACHE;
        }

        return <<<NGINX
# WordPress Nginx Configuration
# Generated by Git Webhook Manager
# Domain: {$domain}
# PHP Version: {$phpVersion}
# FastCGI Cache: {($enableCache ? 'Enabled' : 'Disabled')}
{$cacheConfig}

server {
    listen 80;
    listen [::]:80;
    server_name {$domain};
    
    root {$rootPath};
    index index.php index.html index.htm;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header X-Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;
    
    # Hide Nginx version
    server_tokens off;
{$cacheBypass}
    
    # Access and error logs
    access_log /var/log/nginx/{$domain}-access.log;
    error_log /var/log/nginx/{$domain}-error.log;
    
    # WordPress permalinks
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    
    # PHP processing with FastCGI cache
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:{$socketPath};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # FastCGI settings
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
        fastcgi_read_timeout 300;
{$fastcgiCache}
    }
    
    # Block xmlrpc.php (prevents DDoS attacks)
    location = /xmlrpc.php {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Rate limit wp-login.php (prevent brute-force)
    location = /wp-login.php {
        limit_req zone=login burst=2 nodelay;
        fastcgi_pass unix:{$socketPath};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot|webp)$ {
        expires 365d;
        add_header Cache-Control "public, immutable";
        access_log off;
        log_not_found off;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Protect wp-config.php
    location = /wp-config.php {
        deny all;
    }
    
    # Protect wp-includes PHP files
    location ~* ^/wp-includes/.*\.php$ {
        deny all;
    }
    
    # Protect wp-content uploads from PHP execution
    location ~* ^/wp-content/uploads/.*\.php$ {
        deny all;
    }
    
    # Deny access to wp-content/uploads PHP files
    location ~* /wp-content/.*\.php$ {
        deny all;
    }
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_min_length 1000;
    gzip_proxied any;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript image/svg+xml;
    gzip_disable "MSIE [1-6]\.";
}

# SSL configuration (to be added by Certbot or manually)
# server {
#     listen 443 ssl http2;
#     listen [::]:443 ssl http2;
#     server_name {$domain};
#     
#     ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
#     ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
#     ssl_protocols TLSv1.2 TLSv1.3;
#     ssl_ciphers HIGH:!aNULL:!MD5;
#     ssl_prefer_server_ciphers on;
#     
#     # ... rest of configuration same as above
# }
NGINX;
    }
}
