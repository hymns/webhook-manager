@extends('layouts.app')

@section('title', $website->name . ' - Git Webhook Manager')
@section('page-title', $website->name)
@section('page-description', ucfirst($website->project_type) . ' Website Details')

@section('page-actions')
    <a href="{{ route('websites.index', ['type' => $website->project_type]) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to List
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-globe"></i> Basic Information</h5>
                    <hr class="mt-0 mb-3">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            Name
                        </div>
                        <div class="col-md-8">
                            {{ $website->name }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Domain
                        </div>
                        <div class="col-md-8">
                            <code>{{ $website->domain }}</code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Project Type
                        </div>
                        <div class="col-md-8">
                            <span class="badge badge-pastel-{{ $website->project_type == "php" ? 'purple' : 'green'; }}">
                                {{ $website->project_type === 'php' ? 'PHP' : 'Node.js' }}
                            </span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Version
                        </div>
                        <div class="col-md-8">
                            {{ $website->version_display }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Status
                        </div>
                        <div class="col-md-8">
                            <span class="badge badge-pastel-{{ $website->is_active ? 'green' : 'red' }}">
                                {{ $website->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Nginx Status
                        </div>
                        <div class="col-md-8">
                            <span class="badge badge-pastel-{{ $website->nginx_status == 'active' ? 'green' : 'red' }}">
                                {{ ucfirst($website->nginx_status) }}
                            </span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            SSL Enabled
                        </div>
                        <div class="col-md-8">
                            @if($website->ssl_enabled)
                                <span class="badge badge-paste-green">
                                    <i class="bi bi-shield-check me-1"></i> Yes
                                </span>
                            @else
                                <span class="badge badge-pastel-black">
                                    <i class="bi bi-shield-x me-1"></i> No
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            SSL Status
                        </div>
                        <div class="col-md-8">
                            <span class="badge badge-pastel-{{ $website->ssl_status ? 'green' : 'black' }}">
                                {{ ucfirst($website->ssl_status) }}
                            </span>
                        </div>
                    </div>

                    @if(config('services.cloudflare.enabled'))
                        <div class="row mb-3">
                            <div class="col-md-4">
                                CloudFlare DNS Status
                            </div>
                            <div class="col-md-8">
                                <span class="badge badge-pastel-{{ $website->dns_status === 'active' ? 'green' : 'black' }}">
                                    {{ ucfirst($website->dns_status) }}
                                </span>
                                @if($website->dns_status === 'active' && $website->server_ip)
                                    <small class="text-muted ms-2">→ {{ $website->server_ip }}</small>
                                @endif
                                @if($website->dns_error)
                                    <div class="alert alert-danger alert-sm mt-2 mb-0">
                                        <small>{{ $website->dns_error }}</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($website->project_type === 'node')
                        <div class="row mb-3">
                            <div class="col-md-4">
                                PM2 Status
                            </div>
                            <div class="col-md-8">
                                <span class="badge bg-{{ $website->pm2_status_badge }}">
                                    {{ ucfirst($website->pm2_status) }}
                                </span>
                                @if(config('app.env') === 'local')
                                    <small class="text-muted ms-2">(Control via webhook post-deploy)</small>
                                @endif
                            </div>
                        </div>
                    @endif

                    <hr class="my-4">

                    <!-- Path Configuration -->
                    <h5 class="card-title mb-3"><i class="bi bi-terminal"></i> Path Configuration</h5>

                    <div class="row mb-2 align-items-center">
                        <div class="col-md-4">
                            Root Path
                        </div>
                        <div class="col-md-8">
                            <code>{{ $website->root_path }}</code>
                        </div>
                    </div>

                    <div class="row mb-2 align-items-center">
                        <div class="col-md-4">
                            {{ $website->project_type === 'php' ? 'Working Directory' : 'Run opt' }}
                        </div>
                        <div class="col-md-8">
                            <code>{{ $website->working_directory ?? $website->root_path }}</code>
                        </div>
                    </div>

                    @if($website->project_type === 'node' && $website->port)
                        <div class="row mb-3">
                            <div class="col-md-4">
                                Port
                            </div>
                            <div class="col-md-8">
                                {{ $website->port }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($website->project_type === 'php' && !empty($website->php_settings))
            <!-- PHP Hardening Settings -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-gear"></i> PHP Hardening Settings</h5>
                    <hr class="mt-0 mb-3">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            Memory Limit
                        </div>
                        <div class="col-md-8">
                            <code>{{ $website->php_settings['memory_limit'] ?? '256M' }}</code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Max Execution Time
                        </div>
                        <div class="col-md-8">
                            <code>{{ $website->php_settings['max_execution_time'] ?? '300' }}</code> seconds
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Upload Max Filesize
                        </div>
                        <div class="col-md-8">
                            <code>{{ $website->php_settings['upload_max_filesize'] ?? '100M' }}</code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Post Max Size
                        </div>
                        <div class="col-md-8">
                            <code>{{ $website->php_settings['post_max_size'] ?? '100M' }}</code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Function Security
                        </div>
                        <div class="col-md-8">
                            @php
                                $allDangerousFunctions = ['exec', 'passthru', 'shell_exec', 'system', 'proc_open', 'popen', 'curl_exec', 'curl_multi_exec', 'parse_ini_file', 'show_source'];
                                
                                $disabledFuncs = !empty($website->php_settings['disable_functions']) 
                                    ? array_map('trim', explode(',', $website->php_settings['disable_functions']))
                                    : $allDangerousFunctions;
                                    
                                $enabledFuncs = array_diff($allDangerousFunctions, $disabledFuncs);
                            @endphp
                            
                            @if(count($enabledFuncs) > 0)
                                <div class="mb-2">
                                    <small class="text-danger"><strong>⚠️ Enabled (Security Risk):</strong></small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @foreach($enabledFuncs as $func)
                                            <span class="badge badge-pastel-orange"><code>{{ $func }}()</code></span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            @if(count($disabledFuncs) > 0)
                                <div>
                                    <small class="text-success"><strong>✓ Disabled (Secure):</strong></small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @foreach($disabledFuncs as $func)
                                            <span class="badge badge-pastel-green"><code>{{ $func }}()</code></span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            @if(count($disabledFuncs) === 0)
                                <div class="alert alert-danger mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>Warning:</strong> All dangerous functions are enabled! This is not recommended.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">
                            <i class="bi bi-folder-lock me-1"></i> Path Isolation
                        </div>
                        <div class="col-md-8">
                            @if(!empty($website->php_settings['open_basedir']))
                                <div class="badge badge-pastel-green mb-2">
                                    <i class="bi bi-shield-check me-1"></i> Enabled
                                </div>
                                <div class="font-monospace small text-break">
                                    {{ str_replace(':', ' : ', $website->php_settings['open_basedir']) }}
                                </div>
                                <small class="text-muted">PHP can only access files in these directories</small>
                            @else
                                <div class="badge badge-pastel-green mb-2">
                                    <i class="bi bi-shield-check me-1"></i> Auto-configured
                                </div>
                                <div class="font-monospace small text-break text-muted">
                                    {{ $website->root_path }} : /tmp : /usr/share/php : /usr/share/pear
                                </div>
                                <small class="text-muted">Default paths applied automatically</small>
                            @endif
                        </div>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        <small>These settings are applied in the dedicated PHP-FPM pool: <code>{{ $website->php_pool_name ?? 'default' }}</code></small>
                    </div>
                </div>
            </div>
            @endif

            <!-- Timestamps -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-clock-history"></i> Timestamps</h5>
                    <hr class="mt-0 mb-3">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            Created At
                        </div>
                        <div class="col-md-8">
                            {{ $website->created_at->format('d M Y, h:i A') }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            Last Update
                        </div>
                        <div class="col-md-8">
                            {{ $website->updated_at->format('d M Y, h:i A') }}
                        </div>
                    </div>
                </div>
            </div>

            @if($website->project_type === 'node' && config('app.env') !== 'local')
                <!-- PM2 Process Control -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-hdd-rack me-2"></i>PM2 Process Control
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <strong>Application:</strong> <code>{{ str_replace('.', '-', $website->domain) }}</code>
                                </p>
                                <p class="mb-2">
                                    <strong>Config:</strong> <code>/etc/pm2/ecosystem.{{ str_replace('.', '-', $website->domain) }}.config.js</code>
                                </p>
                                <p class="mb-0">
                                    <strong>Current Status:</strong>
                                    <span class="badge bg-{{ $website->pm2_status_badge }} ms-2">
                                        {{ ucfirst($website->pm2_status) }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group" role="group">
                                    <form action="{{ route('websites.pm2-start', $website) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success" title="Start or restart PM2 application">
                                            <i class="bi bi-play-circle me-1"></i> Start
                                        </button>
                                    </form>
                                    <form action="{{ route('websites.pm2-restart', $website) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-warning" title="Restart PM2 application">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Restart
                                        </button>
                                    </form>
                                    <form action="{{ route('websites.pm2-stop', $website) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-danger" title="Stop PM2 application">
                                            <i class="bi bi-stop-circle me-1"></i> Stop
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> These controls manage the PM2 process directly. For automated deployment, configure the post-deploy script in your webhook settings.
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <!-- Compact Icon Grid -->
                    <div class="d-flex gap-2 flex-wrap justify-content-center mb-3">
                        <!-- SSL Button -->
                        <form action="{{ route('websites.toggle-ssl', $website) }}" method="POST">
                            @csrf
                            @method('POST')
                            <button type="submit" class="action-btn {{ $website->ssl_enabled ? 'active' : '' }}" title="{{ $website->ssl_enabled ? 'SSL Enabled' : 'Enable SSL' }}">
                                <i class="bi bi-shield-check"></i>
                                @if($website->ssl_enabled)
                                    <span class="status-badge"></span>
                                @endif
                            </button>
                        </form>
                        
                        @if(config('services.cloudflare.enabled'))
                        <!-- DNS Button -->
                        <form action="{{ route('websites.dns-sync', $website) }}" method="POST">
                            @csrf
                            <button type="submit" class="action-btn {{ $website->dns_status === 'active' ? 'active' : '' }}" title="{{ $website->dns_status === 'active' ? 'DNS Synced' : 'Sync DNS' }}">
                                <i class="bi bi-cloud"></i>
                            </button>
                        </form>
                        @endif

                        <!-- Redeploy Button -->
                        <form id="redeploy-form" action="{{ route('websites.redeploy', $website) }}" method="POST">
                            @csrf
                            <button type="button" class="action-btn warning" title="Redeploy Configuration"
                                    onclick="confirmAction('Redeploy Configuration', 'Regenerate and redeploy Nginx and PHP-FPM configurations for {{ $website->domain }}?', 'Yes, redeploy!', 'question').then(confirmed => { if(confirmed) this.closest('form').submit(); })">
                                <i class="bi bi-rocket-takeoff"></i>
                            </button>
                        </form>

                        <!-- Edit Button -->
                        <a href="{{ route('websites.edit', $website) }}" class="action-btn primary" title="Edit Website">
                            <i class="bi bi-pencil"></i>
                        </a>

                        <!-- Delete Button -->
                        <button type="button" class="action-btn danger" title="Delete Website" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>

                    <!-- Action Labels -->
                    <div class="text-center">
                        <small class="text-muted" style="font-size: 0.75rem;">Hover for action details</small>
                    </div>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Quick Tips</h5>
                </div>
                <div class="card-body">
                    @if($website->project_type === 'php')
                        <h6>PHP Website</h6>
                        <p class="small">This website uses PHP {{ $website->php_version ?? 'System Default' }} with its own PHP-FPM pool for isolated resource management.</p>
                    @else
                        <h6>Node.js Application</h6>
                        <p class="small">This Node.js app runs on port {{ $website->port }} and is managed by PM2 for automatic restarts and monitoring.</p>
                    @endif

                    <h6 class="mt-3">SSL Certificate</h6>
                    <p class="small">@if($website->ssl_enabled)SSL is enabled. Certificates auto-renew every 90 days via Let's Encrypt.@elseTo enable HTTPS, click the "Enable SSL" button. Let's Encrypt certificate will be requested automatically.@endif</p>

                    @if(config('services.cloudflare.enabled'))
                        <h6 class="mt-3">Cloudflare DNS</h6>
                        <p class="small">@if($website->dns_status === 'active')DNS A record is synced pointing to {{ $website->server_ip }}.@else Click "Sync DNS" to create/update the DNS A record in Cloudflare.@endif</p>
                    @endif

                    <h6 class="mt-3">Redeploy</h6>
                    <p class="small">Use "Redeploy Config" if you've manually changed files or need to regenerate Nginx/PHP-FPM configurations.</p>

                    @if($website->project_type === 'node')
                        <h6 class="mt-3">PM2 Management</h6>
                        <p class="small">Control your Node.js application with Start, Restart, or Stop buttons. Check status before making changes.</p>
                    @endif

                    <h6 class="mt-3">Configuration Files</h6>
                    <p class="small">Nginx: <code>/etc/nginx/sites-available/{{ $website->domain }}</code><br>
                    @if($website->project_type === 'php')PHP-FPM: <code>/etc/php/{{ $website->php_version }}/fpm/pool.d/{{ str_replace('.', '-', $website->domain) }}.conf</code>@endif
                    @if($website->project_type === 'node')PM2: <code>/etc/pm2/ecosystem.{{ str_replace('.', '-', $website->domain) }}.config.js</code>@endif</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong>{{ $website->name }}</strong>?</p>
                    <p class="text-muted small">This will only remove the configuration from the database. The actual files and directories will not be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="{{ route('websites.destroy', $website) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
