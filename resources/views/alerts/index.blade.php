@extends('layouts.app')

@section('title', 'Alerts - Git Webhook Manager')
@section('page-title', 'Alerts & Monitoring')
@section('page-description', 'Monitor system metrics and get notified')

@section('page-actions')
    <a href="{{ route('alerts.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create Alert Rule
    </a>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Alert Rules -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Alert Rules</h5>
    </div>
    <div class="card-body">
        @if($alertRules->isEmpty())
            <p class="text-muted">No alert rules configured.</p>
        @else
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Metric</th>
                            <th>Condition</th>
                            <th>Threshold</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alertRules as $rule)
                            <tr>
                                <td>{{ $rule->name }}</td>
                                <td>
                                    <span class="badge bg-info">{{ ucfirst($rule->metric) }}</span>
                                </td>
                                <td><code>{{ $rule->condition }} {{ $rule->threshold }}%</code></td>
                                <td>{{ $rule->threshold }}{{ $rule->metric === 'disk' ? '%' : ($rule->metric === 'service' ? '' : '%') }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ ucfirst($rule->channel) }}</span>
                                </td>
                                <td>
                                    @if($rule->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('alerts.edit', $rule) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form id="delete-alert-rule-{{ $rule->id }}" action="{{ route('alerts.destroy', $rule) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete('Delete this alert rule? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('delete-alert-rule-{{ $rule->id }}').submit(); })">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Triggered Alerts -->
<div class="card">
    <div class="card-header">
        <h5>Recent Alerts</h5>
    </div>
    <div class="card-body">
        @if($alerts->isEmpty())
            <p class="text-muted">No alerts triggered recently.</p>
        @else
            <div class="list-group">
                @foreach($alerts as $alert)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">{{ $alert->title }}</h6>
                                <p class="mb-1 small">{{ $alert->message }}</p>
                                <small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small>
                            </div>
                            <div>
                                <span class="badge bg-{{ $alert->severity === 'critical' ? 'danger' : ($alert->severity === 'warning' ? 'warning' : 'info') }}">
                                    {{ ucfirst($alert->severity) }}
                                </span>
                                @if(!$alert->is_resolved)
                                    <form action="{{ route('alerts.resolve', $alert) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Resolve</button>
                                    </form>
                                @else
                                    <span class="badge bg-success">Resolved</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3">
                {{ $alerts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
