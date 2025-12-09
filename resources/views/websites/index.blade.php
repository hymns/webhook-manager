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
            font-size: 1rem;
            font-weight: 600;
            color: #3c3c3cff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .website-domain {
            color: #a5a5a5ff;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
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
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 2s infinite;
        }
        .status-dot.inactive {
            background: #6b7280;
        }
        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            50% {
                box-shadow: 0 0 0 4px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
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
            padding: 0.3rem 0;
            font-size: 0.8125rem;
        }
        .info-label {
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-value {
            color: #1a1a1a;
            font-weight: 200;
        }
        .status-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8125rem;
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
            margin-bottom: 2rem;
        }
        .website-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #e8f0ffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #6b7280;
            flex-shrink: 0;
        }
    </style>

    <!-- Tabs -->
    <div class="tabs-container">
        <a href="{{ route('websites.index', ['type' => 'php']) }}" 
           class="tab-btn text-decoration-none {{ $type === 'php' ? 'active' : '' }}">
            <i class="bi bi-code-slash me-1"></i> PHP Projects
        </a>
        <a href="{{ route('websites.index', ['type' => 'node']) }}" 
           class="tab-btn text-decoration-none {{ $type === 'node' ? 'active' : '' }}">
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
                            <div class="website-icon">
                                <i class="bi bi-globe"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="website-name">                                    
                                    {{ $website->name }} <span class="status-dot {{ $website->is_active ? 'active' : 'inactive' }}"></span>
                                    @if($website->ssl_status === 'active')
                                        <span class="badge" style="background: #10b981; font-size: 0.75rem;"><i class="bi bi-lock-fill"></i> SSL</span>                                    
                                    @endif
                                </div>
                                <a href="http://{{ $website->domain }}" target="_blank" class="website-domain">
                                    {{ $website->domain }}
                                    <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                                    <span class="badge badge-pastel-purple">PHP {{ $website->php_version }}</span>
                                </a>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="deploy-btn" 
                                    onclick="event.stopPropagation(); redeployWebsite({{ $website->id }})">
                                <i class="bi bi-rocket-takeoff-fill me-2"></i> Redeploy
                            </button>
                            <div class="dropdown" onclick="event.stopPropagation();">
                                <button class="btn btn-link text-dark p-0" type="button" 
                                        data-bs-toggle="dropdown" style="font-size: 1.25rem;">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('websites.show', $website) }}">
                                        <i class="bi bi-search me-2"></i>View Details
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('websites.edit', $website) }}">
                                        <i class="bi bi-pencil me-2"></i>Edit
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" 
                                           onclick="event.preventDefault(); confirmDelete('Delete {{ $website->name }}? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('delete-form-{{ $website->id }}').submit(); });">
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
                            <span class="info-label"><i class="bi bi-code-slash"></i> Type</span>
                            <span class="info-value">{{ $website->project_type === 'php' ? 'PHP' : 'Node.js' }}</span>
                        </div>
                        @if($website->project_type === 'php')
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-gear"></i> PHP Version</span>
                                <span class="info-value">{{ $website->php_version ?? 'System Default' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-layers"></i> FPM Pool</span>
                                <span class="info-value">{{ $website->php_pool_name ?? 'www' }}</span>
                            </div>
                        @else
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-hexagon"></i> Node Version</span>
                                <span class="info-value">{{ $website->node_version ?? '18.x' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-ethernet"></i> Port</span>
                                <span class="info-value">{{ $website->port ?? '3000' }}</span>
                            </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-folder"></i> Root Path</span>
                            <span class="info-value"><code>{{ $website->root_path }}</code></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-folder-symlink"></i> Working Directory</span>
                            <span class="info-value"><code>{{ $website->working_directory ?? '/' }}</code></span>
                        </div>

                        <!-- Services Section -->
                        <div class="section-label">SERVICES</div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-hexagon"></i> Nginx</span>
                            <span class="badge badge-md badge-pastel-{{ $website->nginx_status === 'active' ? 'green' : ($website->nginx_status === 'pending' ? 'yellow' : 'red') }}">
                                {{ ucfirst($website->nginx_status) }}
                            </span>
                        </div>
                        @if($website->ssl_enabled)
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-shield-lock"></i> SSL/TLS</span>
                                <span class="badge badge-md badge-pastel-{{ $website->ssl_status === 'active' ? 'green' : ($website->ssl_status === 'pending' ? 'yellow' : 'red') }}">
                                    {{ ucfirst($website->ssl_status) }}
                                </span>
                            </div>
                        @endif
                        @if(config('services.cloudflare.enabled') && $website->dns_status !== 'none')
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-cloud"></i> CloudFlare DNS</span>
                                <span class="info-value">
                                    {{ $website->server_ip }} ← 
                                    <span class="badge badge-md badge-pastel-{{ $website->dns_status === 'active' ? 'green' : ($website->dns_status === 'pending' ? 'yellow' : 'red') }}">
                                        {{ ucfirst($website->dns_status) }}
                                    </span>
                                </span>
                            </div>
                        @endif

                        <!-- Deployment Section -->
                        <div class="section-label">DEPLOYMENT</div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-clock-history"></i> Last Deploy</span>
                            <span class="info-value">{{ $website->updated_at->diffForHumans() }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-activity"></i> Status</span>
                            <span class="badge badge-md badge-pastel-{{ $website->is_active ? 'green' : 'red' }}">
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

        async function redeployWebsite(id) {
            const confirmed = await confirmAction('Redeploy Website?', 'Regenerate and redeploy Nginx and PHP-FPM configurations?', 'Yes, redeploy!', 'question');
            if (confirmed) {
                document.getElementById(`redeploy-form-${id}`).submit();
            }
        }
    </script>
@endsection
