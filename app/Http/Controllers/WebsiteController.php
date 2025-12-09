<?php

namespace App\Http\Controllers;

use App\Jobs\DeployNginxConfig;
use App\Jobs\RequestSslCertificate;
use App\Models\Website;
use App\Services\CloudflareService;
use App\Services\Pm2Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebsiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $type = $request->get('type', 'php');
        
        $websites = Website::ofType($type)
            ->latest()
            ->paginate(15);
        
        return view('websites.index', compact('websites', 'type'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $type = $request->get('type', 'php');
        
        // Get available PHP versions (you can customize this list)
        $phpVersions = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
        
        // Get available Node versions (you can customize this list)
        $nodeVersions = ['16.x', '18.x', '20.x', '21.x'];
        
        return view('websites.create', compact('type', 'phpVersions', 'nodeVersions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'unique:websites,domain'],
            'root_path' => ['nullable', 'string', 'max:500'],
            'working_directory' => ['nullable', 'string', 'max:500'],
            'project_type' => ['required', 'in:php,node'],
            'php_version' => ['nullable', 'string', 'max:10'],
            'node_version' => ['nullable', 'string', 'max:10'],
            'php_settings' => ['nullable', 'array'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssl_enabled' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        // Auto-generate root_path if not provided
        if (empty($validated['root_path'])) {
            $validated['root_path'] = $this->generateRootPath($validated['domain']);
        }

        // Set working_directory to '/' if not provided (relative to root_path)
        if (empty($validated['working_directory'])) {
            $validated['working_directory'] = '/';
        }

        // Set SSL to false by default
        $validated['ssl_enabled'] = $request->boolean('ssl_enabled', false);
        $validated['is_active'] = $request->boolean('is_active', true);

        $website = Website::create($validated);

        // Dispatch job to deploy Nginx configuration
        dispatch(new DeployNginxConfig($website));

        // If SSL is enabled, dispatch SSL certificate request
        if ($website->ssl_enabled) {
            dispatch(new RequestSslCertificate($website));
        }

        return redirect()
            ->route('websites.index', ['type' => $website->project_type])
            ->with('success', 'Website created successfully! Nginx configuration is being deployed.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Website $website)
    {
        return view('websites.show', compact('website'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Website $website)
    {
        // Get available PHP versions
        $phpVersions = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
        
        // Get available Node versions
        $nodeVersions = ['16.x', '18.x', '20.x', '21.x'];
        
        return view('websites.edit', compact('website', 'phpVersions', 'nodeVersions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Website $website)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', Rule::unique('websites')->ignore($website->id)],
            'root_path' => ['required', 'string', 'max:500'],
            'working_directory' => ['nullable', 'string', 'max:500'],
            'project_type' => ['required', 'in:php,node'],
            'php_version' => ['nullable', 'string', 'max:10'],
            'node_version' => ['nullable', 'string', 'max:10'],
            'php_settings' => ['nullable', 'array'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssl_enabled' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        // Set working_directory to '/' if not provided (relative to root_path)
        if (empty($validated['working_directory'])) {
            $validated['working_directory'] = '/';
        }

        // Process PHP settings for PHP projects
        if ($validated['project_type'] === 'php') {
            $phpSettings = $validated['php_settings'] ?? [];
            
            // All dangerous functions that should be disabled by default
            $allDangerousFunctions = [
                'exec', 'passthru', 'shell_exec', 'system', 
                'proc_open', 'popen', 'curl_exec', 'curl_multi_exec',
                'parse_ini_file', 'show_source'
            ];
            
            // Get functions user wants to ENABLE
            $enabledFunctions = $request->input('enabled_functions', []);
            
            // Calculate which functions to DISABLE (all dangerous minus enabled)
            $disabledFunctions = array_diff($allDangerousFunctions, $enabledFunctions);
            
            // Store disabled functions in settings
            $phpSettings['disable_functions'] = implode(',', $disabledFunctions);
            
            $validated['php_settings'] = $phpSettings;
        }

        $sslChanged = $request->boolean('ssl_enabled', false) !== $website->ssl_enabled;
        
        $validated['ssl_enabled'] = $request->boolean('ssl_enabled', $website->ssl_enabled);
        $validated['is_active'] = $request->boolean('is_active', $website->is_active);

        $website->update($validated);

        // Redeploy Nginx configuration with updated settings
        dispatch(new DeployNginxConfig($website));

        // If SSL was enabled, request certificate
        if ($sslChanged && $website->ssl_enabled) {
            dispatch(new RequestSslCertificate($website));
        }

        return redirect()
            ->route('websites.index', ['type' => $website->project_type])
            ->with('success', 'Website updated successfully! Configuration is being redeployed.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Website $website)
    {
        $type = $website->project_type;
        
        // Delete DNS record if exists
        if ($website->cloudflare_zone_id && $website->cloudflare_record_id) {
            $this->deleteDnsRecord($website);
        }
        
        // Delete Nginx configuration
        $nginxService = app(\App\Services\NginxService::class);
        $nginxService->deleteConfig($website);
        
        // Delete PHP-FPM pool configuration if PHP project
        if ($website->project_type === 'php') {
            $phpFpmService = app(\App\Services\PhpFpmService::class);
            $phpFpmService->deletePoolConfig($website);
            $phpFpmService->reloadService($website->php_version);
        }
        
        $website->delete();

        return redirect()
            ->route('websites.index', ['type' => $type])
            ->with('success', 'Website and all configurations deleted successfully!');
    }

    /**
     * Toggle SSL for a website.
     */
    public function toggleSsl(Website $website)
    {
        $enableSsl = !$website->ssl_enabled;
        
        $website->update([
            'ssl_enabled' => $enableSsl
        ]);

        if ($enableSsl) {
            // Request SSL certificate
            dispatch(new RequestSslCertificate($website));
            $message = 'SSL certificate request initiated. This may take a few minutes.';
        } else {
            // Just redeploy without SSL
            dispatch(new DeployNginxConfig($website));
            $message = 'SSL disabled. Nginx configuration is being updated.';
        }

        return redirect()
            ->route('websites.index', ['type' => $website->project_type])
            ->with('success', $message);
    }

    /**
     * Redeploy configurations for a website.
     */
    public function redeploy(Website $website)
    {
        // Reset status to pending
        $website->update([
            'nginx_status' => 'pending',
        ]);

        // Dispatch job to redeploy Nginx configuration
        dispatch(new DeployNginxConfig($website));

        // If SSL is enabled, also request SSL certificate
        if ($website->ssl_enabled) {
            dispatch(new RequestSslCertificate($website));
        }

        return redirect()
            ->route('websites.show', $website)
            ->with('success', 'Configuration redeploy has been queued. Please wait for the process to complete.');
    }

    /**
     * Start PM2 application.
     */
    public function pm2Start(Website $website, Pm2Service $pm2Service)
    {
        if ($website->project_type !== 'node') {
            return redirect()
                ->route('websites.show', $website)
                ->with('error', 'PM2 control is only available for Node.js projects.');
        }

        $result = $pm2Service->startApp($website);

        if ($result['success']) {
            return redirect()
                ->route('websites.show', $website)
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('websites.show', $website)
            ->with('error', $result['error']);
    }

    /**
     * Stop PM2 application.
     */
    public function pm2Stop(Website $website, Pm2Service $pm2Service)
    {
        if ($website->project_type !== 'node') {
            return redirect()
                ->route('websites.show', $website)
                ->with('error', 'PM2 control is only available for Node.js projects.');
        }

        $result = $pm2Service->stopApp($website);

        if ($result['success']) {
            return redirect()
                ->route('websites.show', $website)
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('websites.show', $website)
            ->with('error', $result['error']);
    }

    /**
     * Restart PM2 application.
     */
    public function pm2Restart(Website $website, Pm2Service $pm2Service)
    {
        if ($website->project_type !== 'node') {
            return redirect()
                ->route('websites.show', $website)
                ->with('error', 'PM2 control is only available for Node.js projects.');
        }

        $result = $pm2Service->restartApp($website);

        if ($result['success']) {
            return redirect()
                ->route('websites.show', $website)
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('websites.show', $website)
            ->with('error', $result['error']);
    }

    /**
     * Generate root path from domain name.
     */
    protected function generateRootPath(string $domain): string
    {
        // Remove www. prefix if exists
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Convert domain to path-friendly format
        $path = str_replace('.', '_', $domain);
        
        // Default path (you can customize this)
        return '/var/www/' . $path;
    }

    /**
     * Create DNS record for website.
     */
    protected function createDnsRecord(Website $website): void
    {
        try {
            $cloudflare = app(CloudflareService::class);
            
            if (!$cloudflare->isConfigured()) {
                return;
            }

            // Get server IP
            $serverIp = $cloudflare->getServerIp();
            if (!$serverIp) {
                \Log::warning('Failed to detect server IP for DNS record', [
                    'website_id' => $website->id,
                ]);
                return;
            }

            // Update DNS status to pending
            $website->update([
                'dns_status' => 'pending',
                'server_ip' => $serverIp,
            ]);

            // Get zone ID
            $zoneId = $cloudflare->getZoneId($website->domain);
            if (!$zoneId) {
                $website->update([
                    'dns_status' => 'failed',
                    'dns_error' => 'Cloudflare zone not found for domain',
                ]);
                return;
            }

            // Create DNS record
            $result = $cloudflare->createDnsRecord(
                $zoneId,
                $website->domain,
                $serverIp,
                config('services.cloudflare.proxied', false)
            );

            if ($result['success']) {
                $website->update([
                    'cloudflare_zone_id' => $zoneId,
                    'cloudflare_record_id' => $result['record_id'],
                    'dns_status' => 'active',
                    'dns_error' => null,
                    'dns_last_synced_at' => now(),
                ]);

                \Log::info('DNS record created automatically', [
                    'website_id' => $website->id,
                    'domain' => $website->domain,
                    'ip' => $serverIp,
                ]);
            } else {
                $website->update([
                    'cloudflare_zone_id' => $zoneId,
                    'dns_status' => 'failed',
                    'dns_error' => $result['error'] ?? 'Unknown error',
                ]);

                \Log::error('Failed to create DNS record automatically', [
                    'website_id' => $website->id,
                    'domain' => $website->domain,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Exception creating DNS record', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);

            $website->update([
                'dns_status' => 'failed',
                'dns_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete DNS record for website.
     */
    protected function deleteDnsRecord(Website $website): void
    {
        try {
            $cloudflare = app(CloudflareService::class);
            
            if (!$cloudflare->isConfigured()) {
                return;
            }

            $cloudflare->deleteDnsRecord(
                $website->cloudflare_zone_id,
                $website->cloudflare_record_id
            );

            \Log::info('DNS record deleted automatically', [
                'website_id' => $website->id,
                'domain' => $website->domain,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to delete DNS record', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
