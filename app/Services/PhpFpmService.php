<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class PhpFpmService
{
    protected string $poolDirectory;
    protected bool $isLocal;
    protected string $webServerUser;
    protected string $webServerGroup;

    public function __construct()
    {
        $this->isLocal = in_array(config('app.env'), ['local', 'dev', 'development']);
        
        if ($this->isLocal) {
            // Use storage directory for local/dev environments
            $this->poolDirectory = storage_path('server/php/{version}/pool.d');
            $this->ensureLocalDirectories();
        } else {
            // Production path
            $this->poolDirectory = '/etc/php/{version}/fpm/pool.d';
        }

        // Detect web server user and group
        $this->detectWebServerUser();
    }

    /**
     * Detect the web server user and group based on environment.
     */
    protected function detectWebServerUser(): void
    {
        // Try to get from environment variable
        $envUser = env('WEB_SERVER_USER');
        $envGroup = env('WEB_SERVER_GROUP');

        if ($envUser && $envGroup) {
            $this->webServerUser = $envUser;
            $this->webServerGroup = $envGroup;
            return;
        }

        // Auto-detect based on OS
        if ($this->isLocal) {
            // Local development - use current user
            $this->webServerUser = get_current_user();
            $this->webServerGroup = $this->webServerUser;
        } else {
            // Production - try to detect
            // macOS/BSD uses _www or www, Linux uses www-data
            $possibleUsers = ['www-data', 'www', '_www', 'nginx', 'apache'];
            
            foreach ($possibleUsers as $user) {
                // Check if user exists
                $result = Process::run(['id', '-u', $user]);
                if ($result->successful()) {
                    $this->webServerUser = $user;
                    $this->webServerGroup = $user;
                    return;
                }
            }

            // Fallback to www-data
            $this->webServerUser = 'www-data';
            $this->webServerGroup = 'www-data';
        }
    }

    /**
     * Ensure local storage directories exist
     */
    protected function ensureLocalDirectories(): void
    {
        // Will create version-specific directories when needed
        $baseDir = storage_path('server/php');
        if (!File::exists($baseDir)) {
            File::makeDirectory($baseDir, 0755, true);
        }
    }

    /**
     * Get default PHP hardening settings
     */
    public function getDefaultSettings(): array
    {
        return [
            'memory_limit' => '256M',
            'max_execution_time' => '300',
            'upload_max_filesize' => '100M',
            'post_max_size' => '100M',
            'max_input_time' => '60',
            'max_input_vars' => '1000',
            'disable_functions' => implode(',', [
                'exec',
                'passthru',
                'shell_exec',
                'system',
                'proc_open',
                'popen',
                'curl_exec',
                'curl_multi_exec',
                'parse_ini_file',
                'show_source',
            ]),
            'expose_php' => 'Off',
            'display_errors' => 'Off',
            'log_errors' => 'On',
            'allow_url_fopen' => 'On',
            'allow_url_include' => 'Off',
            'file_uploads' => 'On',
            'enable_dl' => 'Off',
        ];
    }

    /**
     * Generate PHP-FPM pool configuration for a website
     */
    public function generatePoolConfig(Website $website): string
    {
        if ($website->project_type !== 'php') {
            throw new \InvalidArgumentException('Website is not a PHP project');
        }

        $poolName = $website->php_pool_name ?? $this->generatePoolName($website);
        $settings = array_merge(
            $this->getDefaultSettings(),
            $website->php_settings ?? []
        );

        $phpAdmin = $this->buildPhpAdminValues($settings);
        
        // Environment-aware paths
        if ($this->isLocal) {
            $socketPath = storage_path("server/php/php{$website->php_version}-fpm-{$poolName}.sock");
            $logDir = storage_path("server/logs/php{$website->php_version}-fpm");
        } else {
            $socketPath = "/var/run/php/php{$website->php_version}-fpm-{$poolName}.sock";
            $logDir = "/var/log/php{$website->php_version}-fpm";
        }

        return <<<POOL
[{$poolName}]
user = {$this->webServerUser}
group = {$this->webServerGroup}

listen = {$socketPath}
listen.owner = {$this->webServerUser}
listen.group = {$this->webServerGroup}
listen.mode = 0660

; Process manager settings
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

; PHP settings
{$phpAdmin}

; Security: Chroot (optional, commented by default)
; chroot = {$website->root_path}

; Access log
access.log = {$logDir}/{$poolName}-access.log
slowlog = {$logDir}/{$poolName}-slow.log
request_slowlog_timeout = 5s

; Environment variables
env[HOSTNAME] = \$HOSTNAME
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp
POOL;
    }

    /**
     * Build php_admin_value and php_admin_flag directives
     */
    protected function buildPhpAdminValues(array $settings): string
    {
        $lines = [];

        foreach ($settings as $key => $value) {
            $directive = in_array(strtolower($value), ['on', 'off', 'true', 'false', '0', '1'])
                ? 'php_admin_flag'
                : 'php_admin_value';

            // Convert boolean strings
            if (in_array(strtolower($value), ['true', 'false'])) {
                $value = strtolower($value) === 'true' ? 'On' : 'Off';
            }

            $lines[] = "{$directive}[{$key}] = {$value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate unique pool name for website
     */
    protected function generatePoolName(Website $website): string
    {
        $domain = str_replace('.', '_', $website->domain);
        return Str::slug($domain, '_');
    }

    /**
     * Write PHP-FPM pool configuration file
     */
    public function writePoolConfig(Website $website): array
    {
        try {
            if ($website->project_type !== 'php') {
                return [
                    'success' => true,
                    'message' => 'Not a PHP project, skipping PHP-FPM pool configuration'
                ];
            }

            $poolName = $website->php_pool_name ?? $this->generatePoolName($website);
            $config = $this->generatePoolConfig($website);
            
            $poolDir = str_replace('{version}', $website->php_version, $this->poolDirectory);
            $filepath = "{$poolDir}/{$poolName}.conf";

            if ($this->isLocal) {
                // Local mode: Direct file operations
                // Create directories
                if (!File::exists($poolDir)) {
                    File::makeDirectory($poolDir, 0755, true);
                }
                
                $logDir = storage_path("server/logs/php{$website->php_version}-fpm");
                if (!File::exists($logDir)) {
                    File::makeDirectory($logDir, 0755, true);
                }
                
                // Write config directly
                File::put($filepath, $config);
                
                $socketPath = storage_path("server/php/php{$website->php_version}-fpm-{$poolName}.sock");
                
                Log::info('[LOCAL] PHP-FPM pool config written to storage', [
                    'filepath' => $filepath,
                    'website_id' => $website->id
                ]);
            } else {
                // Production mode: Use sudo
                // Create log directory if not exists
                $logDir = "/var/log/php{$website->php_version}-fpm";
                Process::run("sudo mkdir -p {$logDir}");
                Process::run("sudo chown www-data:www-data {$logDir}");

                // Write to temporary file first
                $tempFile = tempnam(sys_get_temp_dir(), 'phpfpm_');
                File::put($tempFile, $config);

                // Move to PHP-FPM pool directory with sudo
                $result = Process::run("sudo cp {$tempFile} {$filepath}");
                
                // Clean up temp file
                @unlink($tempFile);
                
                if ($result->failed()) {
                    throw new \Exception("Failed to write pool config: " . $result->errorOutput());
                }

                // Set proper permissions
                Process::run("sudo chmod 644 {$filepath}");
                
                $socketPath = "/var/run/php/php{$website->php_version}-fpm-{$poolName}.sock";
            }

            // Update pool name in database
            if (!$website->php_pool_name) {
                $website->update(['php_pool_name' => $poolName]);
            }

            return [
                'success' => true,
                'filepath' => $filepath,
                'pool_name' => $poolName,
                'socket_path' => $socketPath,
                'message' => 'PHP-FPM pool configuration created successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to write PHP-FPM pool config', [
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
     * Delete PHP-FPM pool configuration
     */
    public function deletePoolConfig(Website $website): array
    {
        try {
            if ($website->project_type !== 'php' || !$website->php_pool_name) {
                return [
                    'success' => true,
                    'message' => 'No PHP-FPM pool to delete'
                ];
            }

            $poolDir = str_replace('{version}', $website->php_version, $this->poolDirectory);
            $filepath = "{$poolDir}/{$website->php_pool_name}.conf";

            if ($this->isLocal) {
                // Local mode: Direct delete
                if (File::exists($filepath)) {
                    File::delete($filepath);
                }
                
                Log::info('[LOCAL] PHP-FPM pool config deleted', ['filepath' => $filepath]);
            } else {
                // Production mode: Use sudo
                $result = Process::run("sudo rm -f {$filepath}");
                
                if ($result->failed()) {
                    throw new \Exception("Failed to delete pool config: " . $result->errorOutput());
                }
            }

            return [
                'success' => true,
                'message' => 'PHP-FPM pool configuration deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete PHP-FPM pool config', [
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
     * Reload PHP-FPM service
     */
    public function reloadService(string $phpVersion): array
    {
        if ($this->isLocal) {
            Log::info("[LOCAL] PHP-FPM reload (skipped) for PHP {$phpVersion}");
            return [
                'success' => true,
                'output' => '[LOCAL] PHP-FPM reload skipped in local environment'
            ];
        }
        
        $result = Process::run("sudo systemctl reload php{$phpVersion}-fpm");

        return [
            'success' => $result->successful(),
            'output' => $result->output()
        ];
    }

    /**
     * Test PHP-FPM configuration
     */
    public function testConfig(string $phpVersion, ?string $poolConfigPath = null): array
    {
        if ($this->isLocal) {
            Log::info("[LOCAL] PHP-FPM config test (skipped) for PHP {$phpVersion}");
            return [
                'success' => true,
                'output' => '[LOCAL] PHP-FPM config test skipped in local environment'
            ];
        }
        
        // Use full path to php-fpm binary as required by sudoers
        $command = "sudo /usr/sbin/php-fpm{$phpVersion} -t";
        
        // If specific pool config path provided, test that specific config
        if ($poolConfigPath && file_exists($poolConfigPath)) {
            $command .= " -y {$poolConfigPath}";
        }
        
        $result = Process::run($command);

        // PHP-FPM -t outputs to stderr, so capture both stdout and stderr
        $output = trim($result->output() . "\n" . $result->errorOutput());

        return [
            'success' => $result->successful(),
            'output' => $output
        ];
    }
}
