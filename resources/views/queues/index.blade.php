@extends('layouts.app')

@section('title', 'Queue Management - Git Webhook Manager')
@section('page-title', 'Queue Management')
@section('page-description', 'Monitor and manage Laravel queues')

@section('content')
    <!-- Queue Driver Info -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-info-circle me-2"></i>
                <strong>Queue Driver:</strong> 
                <code>{{ config('queue.default') }}</code>
                @if(config('queue.default') === 'redis')
                    <span class="badge bg-success ms-2">Redis</span>
                    <small class="d-block mt-1">Connected to Redis at {{ config('database.redis.default.host') }}:{{ config('database.redis.default.port') }}</small>
                @else
                    <span class="badge bg-primary ms-2">Database</span>
                @endif
            </div>
            @if(config('app.env') === 'local')
                <form action="{{ route('queues.dispatch-test') }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="count" value="10">
                    <button type="submit" class="btn btn-sm btn-warning">
                        <i class="bi bi-plus-circle me-1"></i>Dispatch 10 Test Jobs
                    </button>
                </form>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4" style="row-gap: 1rem;">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex" style="flex-direction: row !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                        <div>
                            <h6 class="text-muted mb-2">Pending Jobs</h6>
                            <h2 class="mb-0">{{ $statistics['pending_jobs'] }}</h2>
                        </div>
                        <div class="text-primary" style="font-size: 2.5rem;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex" style="flex-direction: row !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                        <div>
                            <h6 class="text-muted mb-2">Failed Jobs</h6>
                            <h2 class="mb-0">{{ $statistics['failed_jobs'] }}</h2>
                        </div>
                        <div class="text-danger" style="font-size: 2.5rem;">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex" style="flex-direction: row !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                        <div>
                            <h6 class="text-muted mb-2">Failed (24h)</h6>
                            <h2 class="mb-0">{{ $statistics['recent_failed'] }}</h2>
                        </div>
                        <div class="text-warning" style="font-size: 2.5rem;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex" style="flex-direction: row !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                        <div>
                            <h6 class="text-muted mb-2">Queue Types</h6>
                            <h2 class="mb-0">{{ count($statistics['jobs_by_queue']) }}</h2>
                        </div>
                        <div class="text-info" style="font-size: 2.5rem;">
                            <i class="bi bi-layers"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Pending Jobs -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-hourglass-split me-2"></i>Pending Jobs
            </h5>
            <a href="{{ route('queues.pending') }}" class="btn btn-sm btn-outline-primary">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="card-body">
            @if(empty($pendingJobs))
                <p class="text-center text-muted py-4">No pending jobs</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Job Name</th>
                                <th>Queue</th>
                                <th>Attempts</th>
                                <th>Available At</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingJobs as $job)
                                <tr>
                                    <td><code>{{ $job['id'] }}</code></td>
                                    <td>{{ $job['display_name'] }}</td>
                                    <td><span class="badge bg-info">{{ $job['queue'] }}</span></td>
                                    <td>{{ $job['attempts'] }}</td>
                                    <td><small class="text-muted">{{ $job['available_at'] }}</small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('queues.show-job', $job['id']) }}" class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-search"></i>
                                            </a>
                                            @if(config('queue.default') !== 'redis')
                                                <form id="delete-job-form-{{ $job['id'] }}" action="{{ route('queues.delete-job', $job['id']) }}" method="POST" style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-outline-danger" title="Delete" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-job-form-{{ $job['id'] }}').submit(); })">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Failed Jobs -->
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-x-circle me-2"></i>Failed Jobs
            </h5>
            <div class="btn-group btn-group-sm">
                @if(!empty($failedJobs))
                    <form id="retry-all-form" action="{{ route('queues.retry-all-failed') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="button" class="btn btn-warning" onclick="confirmAction('Retry All Failed Jobs?', 'All failed jobs will be retried.', 'Yes, retry all!', 'question').then(confirmed => { if(confirmed) document.getElementById('retry-all-form').submit(); })">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry All
                        </button>
                    </form>
                    <form id="clear-all-form" action="{{ route('queues.clear-failed') }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger" onclick="confirmDelete('Clear all failed jobs? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('clear-all-form').submit(); })">
                            <i class="bi bi-trash me-1"></i>Clear All
                        </button>
                    </form>
                @endif
                <a href="{{ route('queues.failed') }}" class="btn btn-outline-primary">
                    View All <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        <div class="card-body">
            @if(empty($failedJobs))
                <p class="text-center text-muted py-4">No failed jobs</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>UUID</th>
                                <th>Job Name</th>
                                <th>Queue</th>
                                <th>Failed At</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($failedJobs as $job)
                                <tr>
                                    <td><code class="small">{{ substr($job['uuid'], 0, 8) }}...</code></td>
                                    <td>{{ $job['display_name'] }}</td>
                                    <td><span class="badge bg-danger">{{ $job['queue'] }}</span></td>
                                    <td><small class="text-muted">{{ $job['failed_at'] }}</small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('queues.show-failed-job', $job['uuid']) }}" class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-search"></i>
                                            </a>
                                            <form action="{{ route('queues.retry-failed-job', $job['uuid']) }}" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-warning" title="Retry">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            </form>
                                            <form id="delete-failed-job-form-{{ $job['uuid'] }}" action="{{ route('queues.delete-failed-job', $job['uuid']) }}" method="POST" style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-outline-danger" title="Delete" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-failed-job-form-{{ $job['uuid'] }}').submit(); })">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
