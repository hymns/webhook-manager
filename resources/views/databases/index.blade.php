@extends('layouts.app')

@section('title', 'Databases - Git Webhook Manager')
@section('page-title', 'Database Management')
@section('page-description', 'Manage MySQL databases and users')

@section('page-actions')
    <div class="btn-group">
        @if($permissions['can_create'])
            <a href="{{ route('databases.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Create Database
            </a>
        @else
            <button class="btn btn-primary" disabled title="Insufficient MySQL privileges">
                <i class="bi bi-plus-circle me-1"></i> Create Database
            </button>
        @endif
        
        @if(!$permissions['can_create'])
            <a href="{{ route('databases.recheck-permissions') }}" class="btn btn-outline-secondary" title="Recheck permissions">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
        @endif
    </div>
@endsection

@section('content')
    @if(!$permissions['can_create'])
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Insufficient Permissions:</strong> {{ $permissions['message'] }}
            <br>
            <small class="mt-2 d-block">
                <strong>Current User:</strong> <code>{{ $permissions['current_user'] ?? 'Unknown' }}</code><br>
                <strong>Missing Privileges:</strong>
                @foreach($permissions['missing_privileges'] ?? [] as $privilege)
                    <span class="badge bg-danger">{{ $privilege }}</span>
                @endforeach
            </small>
            @if(!empty($permissions['grants']))
                <details class="mt-2" open>
                    <summary class="cursor-pointer small"><strong>View Current Grants (Debug)</strong></summary>
                    <div class="mt-2 p-2 bg-light rounded">
                        @foreach($permissions['grants'] as $grant)
                            <code class="d-block small text-dark mb-1">{{ $grant }}</code>
                        @endforeach
                    </div>
                </details>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    
    {{-- Debug Info (only in local environment) --}}
    @if(config('app.env') === 'local' && !empty($permissions['grants']))
        <div class="alert alert-info">
            <strong>Debug Info (Local Only):</strong><br>
            <small>
                has_create_db: {{ $permissions['has_create_db'] ? 'true' : 'false' }}<br>
                has_create_user: {{ $permissions['has_create_user'] ? 'true' : 'false' }}<br>
                has_grant_option: {{ $permissions['has_grant_option'] ? 'true' : 'false' }}
            </small>
        </div>
    @endif

    @if($databases->isEmpty())
        <div class="database-card">
            <div class="text-center py-5" style="padding: 3rem 1.5rem;">
                <i class="bi bi-database text-muted" style="font-size: 4rem;"></i>
                <h4 class="mt-4">No databases yet</h4>
                <p class="text-muted">Create your first database to get started.</p>
                @if($permissions['can_create'])
                    <a href="{{ route('databases.create') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle me-1"></i> Create Your First Database
                    </a>
                @else
                    <button class="btn btn-primary mt-3" disabled title="Insufficient MySQL privileges">
                        <i class="bi bi-plus-circle me-1"></i> Create Your First Database
                    </button>
                    <p class="text-danger mt-3 small">You don't have sufficient MySQL privileges to create databases.</p>
                @endif
            </div>
        </div>
    @else
        @foreach($databases as $database)
            <div class="database-card">
                <!-- Card Header -->
                <div class="database-card-header" onclick="toggleCard({{ $database->id }})">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-start gap-3" style="flex: 1;">
                            <i class="bi bi-chevron-right chevron-icon" id="chevron-{{ $database->id }}" 
                               style="font-size: 1.25rem; color: #9ca3af; margin-top: 0.25rem;"></i>
                            <div class="database-icon">
                                <i class="bi bi-database"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="database-name">
                                    {{ $database->name }}
                                    <span class="status-dot {{ $database->exists_in_mysql ? 'active' : 'inactive' }}"></span>
                                </div>
                                <div class="database-username">
                                    {{ $database->host }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="dropdown" onclick="event.stopPropagation();">
                                <button class="btn btn-link text-dark p-0" type="button" 
                                        data-bs-toggle="dropdown" style="font-size: 1.25rem;">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('databases.show', $database) }}">
                                        <i class="bi bi-search me-2"></i>View Details
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('databases.change-password', $database) }}">
                                        <i class="bi bi-lock-fill me-2"></i>Change Password
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" 
                                           onclick="event.preventDefault(); confirmDelete('Delete {{ $database->name }}? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('delete-form-{{ $database->id }}').submit(); });">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Body (Collapsible) -->
                <div id="card-body-{{ $database->id }}" style="display: none;">
                    <div class="database-card-body">
                        <!-- Database Information -->
                        <div class="section-label">DATABASE INFORMATION</div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-server"></i> Database Name</span>
                            <span class="info-value"><code>{{ $database->name }}</code></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-person"></i> Username</span>
                            <span class="info-value"><code>{{ $database->username }}</code></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-hdd-network"></i> Host</span>
                            <span class="info-value">{{ $database->host }}</span>
                        </div>
                        @if($database->description)
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-card-text"></i> Description</span>
                            <span class="info-value">{{ $database->description }}</span>
                        </div>
                        @endif

                        <!-- Statistics -->
                        <div class="section-label">STATISTICS</div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-activity"></i> Status</span>
                            <span class="badge badge-md badge-pastel-{{ $database->exists_in_mysql ? 'green' : 'red' }}">
                                {{ $database->exists_in_mysql ? 'Active' : 'Not Found' }}
                            </span>
                        </div>
                        @if($database->exists_in_mysql)
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-hdd"></i> Database Size</span>
                            <span class="info-value">{{ $database->size_mb }} MB</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-table"></i> Table Count</span>
                            <span class="info-value">{{ $database->table_count }} tables</span>
                        </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-calendar"></i> Created</span>
                            <span class="info-value">{{ $database->created_at->format('M d, Y H:i') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-clock-history"></i> Last Updated</span>
                            <span class="info-value">{{ $database->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Hidden Forms -->
                <form id="delete-form-{{ $database->id }}" action="{{ route('databases.destroy', $database) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        @endforeach

        <!-- Pagination -->
        @if($databases->hasPages())
            <div class="mt-4">
                {{ $databases->links() }}
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
    </script>
@endsection
