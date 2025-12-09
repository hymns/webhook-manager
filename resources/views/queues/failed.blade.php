@extends('layouts.app')

@section('title', 'Failed Jobs - Queue Management')
@section('page-title', 'Failed Jobs')
@section('page-description', 'Jobs that failed to process')

@section('page-actions')
    <div class="btn-group">
        @if(!empty($jobs))
            <form id="retry-all-failed-form" action="{{ route('queues.retry-all-failed') }}" method="POST" class="d-inline">
                @csrf
                <button type="button" class="btn btn-warning" onclick="confirmAction('Retry All Failed Jobs?', 'All failed jobs will be retried.', 'Yes, retry all!', 'question').then(confirmed => { if(confirmed) document.getElementById('retry-all-failed-form').submit(); })">
                    <i class="bi bi-arrow-clockwise me-1"></i> Retry All
                </button>
            </form>
            <form id="clear-all-failed-form" action="{{ route('queues.clear-failed') }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="button" class="btn btn-danger" onclick="confirmDelete('Clear all failed jobs? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('clear-all-failed-form').submit(); })">
                    <i class="bi bi-trash me-1"></i> Clear All
                </button>
            </form>
        @endif
        <a href="{{ route('queues.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            @if(empty($jobs))
                <div class="text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-4">No failed jobs</h4>
                    <p class="text-muted">Great! All jobs are processing successfully.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>UUID</th>
                                <th>Job Name</th>
                                <th>Queue</th>
                                <th>Failed At</th>
                                <th>Exception</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobs as $job)
                                <tr>
                                    <td><code class="small">{{ substr($job['uuid'], 0, 8) }}...</code></td>
                                    <td>
                                        <strong>{{ $job['display_name'] }}</strong>
                                    </td>
                                    <td><span class="badge bg-danger">{{ $job['queue'] }}</span></td>
                                    <td><small class="text-muted">{{ $job['failed_at'] }}</small></td>
                                    <td>
                                        <small class="text-danger">
                                            {{ Str::limit(explode("\n", $job['exception'])[0], 50) }}
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('queues.show-failed-job', $job['uuid']) }}" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-search"></i>
                                            </a>
                                            <form action="{{ route('queues.retry-failed-job', $job['uuid']) }}" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-warning" title="Retry">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            </form>
                                            <form id="delete-failed-{{ $job['uuid'] }}" action="{{ route('queues.delete-failed-job', $job['uuid']) }}" method="POST" style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-outline-danger" title="Delete" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-failed-{{ $job['uuid'] }}').submit(); })">
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

                <div class="mt-3">
                    <p class="text-muted mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Showing {{ count($jobs) }} failed job(s)
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection
