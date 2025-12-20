@extends('layouts.app')

@section('title', 'Firewall - Hostiqo')
@section('page-title', 'Firewall')
@section('page-description')
Manage {{ $firewallType === 'firewalld' ? 'firewalld' : 'UFW' }} firewall rules and network security
@endsection

@section('page-actions')
    @if($firewallStatus['enabled'] ?? false)
        <form action="{{ route('firewall.disable') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-shield-slash"></i> Disable {{ $firewallType === 'firewalld' ? 'Firewalld' : 'UFW' }}
            </button>
        </form>
    @else
        <form action="{{ route('firewall.enable') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="bi bi-shield-check"></i> Enable {{ $firewallType === 'firewalld' ? 'Firewalld' : 'UFW' }}
            </button>
        </form>
    @endif
    
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
        <i class="bi bi-plus-circle"></i> Add Rule
    </button>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Firewall Status -->
<div class="card mb-4">
    <div class="card-body">
        <h5>{{ $firewallType === 'firewalld' ? 'Firewalld' : 'UFW' }} Status</h5>
        <div class="alert alert-{{ $firewallStatus['enabled'] ?? false ? 'success' : 'warning' }}">
            Status: <strong>{{ $firewallStatus['enabled'] ?? false ? 'Active' : 'Inactive' }}</strong>
        </div>
    </div>
</div>

<!-- Rules List -->
<div class="card">
    <div class="card-header">
        <h5>Firewall Rules</h5>
    </div>
    <div class="card-body">
        @if($rules->isEmpty())
            <p class="text-muted">No firewall rules configured.</p>
        @else
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Action</th>
                            <th>Port</th>
                            <th>Protocol</th>
                            <th>From IP</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                            <tr>
                                <td>{{ $rule->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $rule->action === 'allow' ? 'success' : 'danger' }}">
                                        {{ ucfirst($rule->action) }}
                                    </span>
                                </td>
                                <td>{{ $rule->port ?? 'Any' }}</td>
                                <td>{{ $rule->protocol ?? 'Any' }}</td>
                                <td>{{ $rule->from_ip ?? 'Any' }}</td>
                                <td>
                                    @if($rule->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$rule->is_system)
                                        <form id="delete-firewall-rule-{{ $rule->id }}" action="{{ route('firewall.destroy', $rule) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete('Delete this firewall rule?').then(confirmed => { if(confirmed) document.getElementById('delete-firewall-rule-{{ $rule->id }}').submit(); })">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">System</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Add Rule Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('firewall.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Firewall Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name (Optional)</label>
                        <input type="text" name="name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select" required>
                            <option value="allow">Allow</option>
                            <option value="deny">Deny</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="text" name="port" class="form-control" placeholder="80, 443, 22, etc">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Protocol</label>
                        <select name="protocol" class="form-select">
                            <option value="">Any</option>
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">From IP (Optional)</label>
                        <input type="text" name="from_ip" class="form-control" placeholder="192.168.1.1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Direction</label>
                        <select name="direction" class="form-select" required>
                            <option value="in">Incoming</option>
                            <option value="out">Outgoing</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
