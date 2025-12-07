@extends('layouts.app')

@section('title', 'Websites - Git Webhook Manager')
@section('page-title', 'Websites')
@section('page-description', 'Manage PHP and Node.js website configurations')

@section('page-actions')
    <a href="{{ route('websites.create', ['type' => $type]) }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Add {{ ucfirst($type) }} Website
    </a>
@endsection

@section('content')
    <!-- Tabs for Project Types -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link {{ $type === 'php' ? 'active' : '' }}" 
               href="{{ route('websites.index', ['type' => 'php']) }}">
                <i class="bi bi-code-slash me-1"></i> PHP Projects
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $type === 'node' ? 'active' : '' }}" 
               href="{{ route('websites.index', ['type' => 'node']) }}">
                <i class="bi bi-hexagon me-1"></i> Node Projects
            </a>
        </li>
    </ul>

    @if(in_array(config('app.env'), ['local', 'dev', 'development']))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <h5 class="alert-heading">
                <i class="bi bi-info-circle me-2"></i>Development Mode Active
            </h5>
            <p class="mb-2">
                <strong>APP_ENV={{ config('app.env') }}</strong> - All configurations are written to local storage instead of system directories.
            </p>
            <hr>
            <p class="mb-1"><strong>Check generated configurations:</strong></p>
            <ul class="mb-2">
                <li><strong>Nginx configs:</strong> <code>storage/server/nginx/sites-available/{domain}.conf</code></li>
                @if($type === 'php')
                    <li><strong>PHP-FPM pools:</strong> <code>storage/server/php/{version}/pool.d/{pool}.conf</code></li>
                    <li><strong>Webroot:</strong> <code>storage/server/www/{domain}/</code></li>
                @else
                    <li><strong>PM2 ecosystem:</strong> <code>storage/server/pm2/ecosystem.{domain}.config.js</code></li>
                    <li><strong>Nginx proxy:</strong> Forwards to <code>localhost:{port}</code></li>
                    <li><strong>Node version:</strong> Configured in PM2 ecosystem file</li>
                @endif
                <li><strong>Logs:</strong> <code>storage/server/logs/nginx/</code>@if($type === 'node'), <code>logs/pm2/</code>@endif</li>
            </ul>
            <p class="mb-0 text-muted small">
                <i class="bi bi-shield-check me-1"></i>
                No system files will be modified. Set <code>APP_ENV=production</code> in <code>.env</code> to deploy to actual server directories.
            </p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            @if($websites->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-globe text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-4">No {{ ucfirst($type) }} websites yet</h4>
                    <p class="text-muted">Create your first {{ $type }} website to get started.</p>
                    <a href="{{ route('websites.create', ['type' => $type]) }}" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle me-1"></i> Add {{ ucfirst($type) }} Website
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Domain</th>
                                <th>Version</th>
                                <th>Nginx</th>
                                <th>SSL</th>
                                @if(config('services.cloudflare.enabled'))
                                    <th>CloudFlare DNS</th>
                                @endif
                                <th>Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($websites as $website)
                                <tr>
                                    <td>
                                        <strong>{{ $website->name }}</strong>
                                    </td>
                                    <td>
                                        <code>{{ $website->domain }}</code>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $website->project_type_badge }}">
                                            {{ $website->project_type === 'php' ? 'PHP' : 'Node.js' }}
                                            {{ $website->version_display }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $website->nginx_status_badge }}" title="Nginx Status">
                                            {{ ucfirst($website->nginx_status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <form action="{{ route('websites.toggle-ssl', $website) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('POST')
                                                <button type="submit" 
                                                        class="btn btn-link p-0 text-{{ $website->ssl_enabled ? 'success' : 'secondary' }}"
                                                        style="font-size: 1.2rem; text-decoration: none;"
                                                        title="{{ $website->ssl_enabled ? 'SSL Enabled - Click to disable' : 'Click to enable SSL' }}"
                                                        {{ $website->ssl_status === 'pending' ? 'disabled' : '' }}>
                                                    <i class="bi bi-{{ $website->ssl_enabled ? 'shield-check-fill' : 'shield-x' }}"></i>
                                                </button>
                                            </form>
                                            @if($website->ssl_status !== 'none')
                                                <span class="badge bg-{{ $website->ssl_status_badge }}" title="SSL Status">
                                                    {{ ucfirst($website->ssl_status) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    @if(config('services.cloudflare.enabled'))
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <form action="{{ route('websites.dns-sync', $website) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" 
                                                            class="btn btn-link p-0 text-{{ $website->dns_status === 'active' ? 'success' : 'secondary' }}"
                                                            style="font-size: 1.2rem; text-decoration: none;"
                                                            title="{{ $website->dns_status === 'active' ? 'DNS Active - Click to resync' : 'Click to create DNS record' }}"
                                                            {{ $website->dns_status === 'pending' ? 'disabled' : '' }}>
                                                        <i class="bi bi-{{ $website->dns_status === 'active' ? 'cloud-check' : 'cloud-slash' }}"></i>
                                                    </button>
                                                </form>
                                                @if($website->dns_status !== 'none')
                                                    <span class="badge bg-{{ $website->dns_status_badge }}" title="DNS Status">
                                                        {{ ucfirst($website->dns_status) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                    <td>
                                        <span class="badge bg-{{ $website->status_badge }}">
                                            {{ $website->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('websites.show', $website) }}" 
                                               class="btn btn-outline-primary"
                                               title="View">
                                                <i class="bi bi-search"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-outline-primary"
                                                    title="Redeploy Configuration"
                                                    onclick="confirmAction('Redeploy Configuration', 'Regenerate and redeploy Nginx and PHP-FPM configurations for {{ $website->domain }}?', 'Yes, redeploy!', 'question').then(confirmed => { if(confirmed) document.getElementById('redeploy-form-{{ $website->id }}').submit(); })">
                                                <i class="bi bi-rocket-takeoff-fill"></i>
                                            </button>
                                            <a href="{{ route('websites.edit', $website) }}" 
                                               class="btn btn-outline-primary"
                                               title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal{{ $website->id }}"
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Hidden redeploy form -->
                                        <form id="redeploy-form-{{ $website->id }}" action="{{ route('websites.redeploy', $website) }}" method="POST" class="d-none">
                                            @csrf
                                        </form>

                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal{{ $website->id }}" tabindex="-1">
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
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $websites->appends(['type' => $type])->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
