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
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{$socketPath};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        include fastcgi_params;
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
    
    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
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
                        // Set ownership to www-data
                        Process::run("sudo chown -R www-data:www-data {$documentRoot}");
                        Process::run("sudo chmod -R 755 {$documentRoot}");
                        
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
        return $website->domain . '.conf';
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
}
