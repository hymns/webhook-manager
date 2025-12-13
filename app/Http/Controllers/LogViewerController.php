<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class LogViewerController extends Controller
{
    /**
     * Display log viewer
     */
    public function index(Request $request)
    {
        $logType = $request->get('type', 'laravel');
        $search = $request->get('search');
        $websiteId = $request->get('website_id');

        $logs = [];
        $logFile = null;
        $website = null;
        
        // Get website if ID provided
        if ($websiteId) {
            $website = Website::find($websiteId);
        }
        
        // Get all websites for dropdown
        $websites = Website::orderBy('name')->get();

        switch ($logType) {
            case 'laravel':
                $logFile = storage_path('logs/laravel.log');
                break;
            case 'queue':
                $logFile = storage_path('logs/queue-worker.log');
                break;
            case 'scheduler':
                $logFile = storage_path('logs/scheduler.log');
                break;
            case 'nginx-access':
                $logFile = '/var/log/nginx/access.log';
                break;
            case 'nginx-error':
                $logFile = '/var/log/nginx/error.log';
                break;
            case 'php-fpm':
                $logFile = '/var/log/php-fpm.log';
                break;
            case 'system':
                $logFile = '/var/log/syslog';
                break;
            
            // Website-specific logs
            case 'website-nginx-access':
                if ($website) {
                    $logFile = "/var/log/nginx/{$website->domain}-access.log";
                }
                break;
            case 'website-nginx-error':
                if ($website) {
                    $logFile = "/var/log/nginx/{$website->domain}-error.log";
                }
                break;
            case 'website-php-access':
                if ($website && $website->project_type === 'php') {
                    $poolName = $website->php_pool_name ?? str_replace(['.', '-'], '_', $website->domain);
                    $logFile = "/var/log/php{$website->php_version}-fpm/{$poolName}-access.log";
                }
                break;
            case 'website-php-slow':
                if ($website && $website->project_type === 'php') {
                    $poolName = $website->php_pool_name ?? str_replace(['.', '-'], '_', $website->domain);
                    $logFile = "/var/log/php{$website->php_version}-fpm/{$poolName}-slow.log";
                }
                break;
            case 'website-laravel':
                if ($website && $website->project_type === 'php') {
                    $laravelLogPath = rtrim($website->working_directory ?: $website->root_path, '/') . '/storage/logs/laravel.log';
                    if (file_exists($laravelLogPath)) {
                        $logFile = $laravelLogPath;
                    } else {
                        session()->flash('warning', "Laravel log not found at: {$laravelLogPath}");
                    }
                } elseif ($website && $website->project_type !== 'php') {
                    session()->flash('warning', 'Laravel logs are only available for PHP websites.');
                }
                break;
        }

        if ($logFile) {
            // Determine if we need sudo (for system logs outside storage/)
            $needsSudo = !str_starts_with($logFile, storage_path());
            $command = $needsSudo 
                ? "sudo tail -n 1000 {$logFile}" 
                : "tail -n 1000 {$logFile}";
            
            // Read last 1000 lines using Process (bypasses open_basedir restrictions)
            $result = Process::run($command);
            
            if ($result->successful()) {
                $content = $result->output();
                $lines = explode("\n", $content);
                $lines = array_reverse($lines); // Latest first

                // Filter by search
                if ($search) {
                    $lines = array_filter($lines, function($line) use ($search) {
                        return stripos($line, $search) !== false;
                    });
                }

                $logs = array_slice($lines, 0, 500); // Limit to 500 lines
            } elseif ($result->failed()) {
                // Log file not accessible or doesn't exist
                $errorMessage = $result->errorOutput();
                
                // Show user-friendly error in view
                session()->flash('error', "Unable to read log file: " . basename($logFile) . ". " . 
                    (str_contains($errorMessage, 'No such file') ? 'File does not exist.' : 'Permission denied or file not accessible.'));
            }
        }

        return view('logs.index', compact('logs', 'logType', 'search', 'websites', 'website'));
    }

    /**
     * Clear log file
     */
    public function clear(Request $request)
    {
        $logType = $request->input('type', 'laravel');
        
        // Determine log file path
        $logFile = match($logType) {
            'queue' => storage_path('logs/queue-worker.log'),
            'scheduler' => storage_path('logs/scheduler.log'),
            default => storage_path('logs/laravel.log'),
        };
        
        $logName = match($logType) {
            'queue' => 'Queue Worker',
            'scheduler' => 'Scheduler',
            default => 'Laravel',
        };
        
        try {
            // Try to check if file exists (may fail with open_basedir restrictions)
            if (file_exists($logFile)) {
                File::put($logFile, '');
            } else {
                // File doesn't exist, create it empty
                File::put($logFile, '');
            }
        } catch (\Exception $e) {
            // If open_basedir restriction, try to clear anyway via Process with sudo
            $result = Process::run("sudo truncate -s 0 {$logFile}");
            
            if ($result->failed()) {
                return back()->with('error', 'Unable to clear log file: ' . $e->getMessage());
            }
        }

        return back()->with('success', $logName . ' log cleared successfully');
    }
}
