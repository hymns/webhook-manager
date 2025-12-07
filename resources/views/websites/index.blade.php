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
    <style>
        .website-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }
        .website-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .website-card-header {
            padding: 1.25rem 1.5rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        .website-card-body {
            padding: 1.5rem;
        }
        .website-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .website-domain {
            color: #5865f2;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }
        .website-domain:hover {
            text-decoration: underline;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-dot.active {
            background: #10b981;
        }
        .status-dot.inactive {
            background: #6b7280;
        }
        .section-label {
            font-size: 0.6875rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
            margin-top: 1.5rem;
        }
        .section-label:first-child {
            margin-top: 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 0.9375rem;
        }
        .info-label {
            color: #6b7280;
        }
        .info-value {
            color: #1a1a1a;
            font-weight: 500;
        }
        .status-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-badge.failed {
            background: #fee2e2;
            color: #991b1b;
        }
        .chevron-icon {
            transition: transform 0.2s;
        }
        .chevron-icon.expanded {
            transform: rotate(90deg);
        }
        .deploy-btn {
            background: #f0f0f0;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #1a1a1a;
            cursor: pointer;
            transition: background 0.2s;
        }
        .deploy-btn:hover {
            background: #e0e0e0;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: #5865f2;
            border-bottom-color: #5865f2;
        }
        .tabs-container {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }
    </style>

    <!-- Tabs -->
    <div class="tabs-container">
        <a href="{{ route('websites.index', ['type' => 'php']) }}" 
           class="tab-btn {{ $type === 'php' ? 'active' : '' }}">
            <i class="bi bi-code-slash me-1"></i> PHP Projects
        </a>
        <a href="{{ route('websites.index', ['type' => 'node']) }}" 
           class="tab-btn {{ $type === 'node' ? 'active' : '' }}">
            <i class="bi bi-hexagon me-1"></i> Node Projects
        </a>
    </div>

    @if($websites->isEmpty())
        <div class="website-card">
            <div class="text-center py-5" style="padding: 3rem 1.5rem;">
                <i class="bi bi-globe text-muted" style="font-size: 4rem;"></i>
                <h4 class="mt-4">No {{ ucfirst($type) }} websites yet</h4>
                <p class="text-muted">Create your first {{ $type }} website to get started.</p>
                <a href="{{ route('websites.create', ['type' => $type]) }}" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-circle me-1"></i> Add {{ ucfirst($type) }} Website
                </a>
            </div>
        </div>
    @else
        @foreach($websites as $website)
            <div class="website-card">
                <!-- Card Header -->
                <div class="website-card-header" onclick="toggleCard({{ $website->id }})">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-start gap-3" style="flex: 1;">
                            <i class="bi bi-chevron-right chevron-icon" id="chevron-{{ $website->id }}" 
                               style="font-size: 1.25rem; color: #9ca3af; margin-top: 0.25rem;"></i>
                            <div class="flex-grow-1">
                                <div class="website-name">
                                    <span class="status-dot {{ $website->is_active ? 'active' : 'inactive' }}"></span>
                                    {{ $website->name }}
                                    @if($website->project_type === 'php')
                                        <span class="badge bg-light text-dark" style="font-size: 0.75rem; font-weight: 500;">PHP</span>
                                    @else
                                        <span class="badge" style="background: #10b981; font-size: 0.75rem;">SSL</span>
                                    @endif
                                </div>
                                <a href="http://{{ $website->domain }}" target="_blank" class="website-domain">
                                    {{ $website->domain }}
                                    <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                                </a>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="deploy-btn" 
                                    onclick="event.stopPropagation(); redeployWebsite({{ $website->id }})">
                                Deploy
                            </button>
                            <div class="dropdown" onclick="event.stopPropagation();">
                                <button class="btn btn-link text-dark p-0" type="button" 
                                        data-bs-toggle="dropdown" style="font-size: 1.25rem;">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('websites.show', $website) }}">
                                        <i class="bi bi-eye me-2"></i>View Details
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('websites.edit', $website) }}">
                                        <i class="bi bi-pencil me-2"></i>Edit
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" 
                                           onclick="event.preventDefault(); if(confirm('Delete {{ $website->name }}?')) document.getElementById('delete-form-{{ $website->id }}').submit();">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Body (Collapsible) -->
                <div id="card-body-{{ $website->id }}" style="display: none;">
                    <div class="website-card-body">
                        <!-- Configuration Section -->
                        <div class="section-label">CONFIGURATION</div>
                        <div class="info-row">
                            <span class="info-label">Type</span>
                            <span class="info-value">{{ $website->project_type === 'php' ? 'PHP' : 'Node.js' }}</span>
                        </div>
                        @if($website->project_type === 'php')
                            <div class="info-row">
                                <span class="info-label">PHP Version</span>
                                <span class="info-value">{{ $website->php_version ?? 'System Default' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">FPM Pool</span>
                                <span class="info-value">{{ $website->php_pool_name ?? 'www' }}</span>
                            </div>
                        @else
                            <div class="info-row">
                                <span class="info-label">Node Version</span>
                                <span class="info-value">{{ $website->node_version ?? '18.x' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Port</span>
                                <span class="info-value">{{ $website->port ?? '3000' }}</span>
                            </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label">Root Path</span>
                            <span class="info-value"><code>{{ $website->root_path }}</code></span>
                        </div>

                        <!-- Services Section -->
                        <div class="section-label">SERVICES</div>
                        <div class="info-row">
                            <span class="info-label">Nginx</span>
                            <span class="status-badge {{ $website->nginx_status === 'active' ? '' : ($website->nginx_status === 'pending' ? 'pending' : 'failed') }}">
                                {{ ucfirst($website->nginx_status) }}
                            </span>
                        </div>
                        @if($website->ssl_enabled)
                            <div class="info-row">
                                <span class="info-label">SSL/TLS</span>
                                <span class="status-badge {{ $website->ssl_status === 'active' ? '' : ($website->ssl_status === 'pending' ? 'pending' : 'failed') }}">
                                    {{ ucfirst($website->ssl_status) }}
                                </span>
                            </div>
                        @endif
                        @if(config('services.cloudflare.enabled') && $website->dns_status !== 'none')
                            <div class="info-row">
                                <span class="info-label">CloudFlare DNS</span>
                                <span class="status-badge {{ $website->dns_status === 'active' ? '' : ($website->dns_status === 'pending' ? 'pending' : 'failed') }}">
                                    {{ ucfirst($website->dns_status) }}
                                </span>
                            </div>
                        @endif

                        <!-- Deployment Section -->
                        <div class="section-label">DEPLOYMENT</div>
                        <div class="info-row">
                            <span class="info-label">Last Deploy</span>
                            <span class="info-value">{{ $website->updated_at->diffForHumans() }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status</span>
                            <span class="status-badge {{ $website->is_active ? '' : 'failed' }}">
                                {{ $website->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Hidden Forms -->
                <form id="delete-form-{{ $website->id }}" action="{{ route('websites.destroy', $website) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
                <form id="redeploy-form-{{ $website->id }}" action="{{ route('websites.redeploy', $website) }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        @endforeach

        <!-- Pagination -->
        @if($websites->hasPages())
            <div class="mt-4">
                {{ $websites->appends(['type' => $type])->links() }}
            </div>
        @endif
    @endif

    <script>
        function toggleCard(id) {
            const body = document.getElementById(`card-body-${id}`);
            const chevron = document.getElementById(`chevron-${id}`);
            
            if (body.style.display === 'none') {
                body.style.display = 'block';
                chevron.classList.add('expanded');
            } else {
                body.style.display = 'none';
                chevron.classList.remove('expanded');
            }
        }

        function redeployWebsite(id) {
            if (confirm('Redeploy this website configuration?')) {
                document.getElementById(`redeploy-form-${id}`).submit();
            }
        }
    </script>
@endsection
