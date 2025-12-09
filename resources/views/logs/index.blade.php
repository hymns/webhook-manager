@extends('layouts.app')

@section('title', 'Logs - Git Webhook Manager')
@section('page-title', 'Log Viewer')
@section('page-description', 'View and search application and system logs')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Log Type Selector -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Log Type</label>
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="laravel" {{ $logType === 'laravel' ? 'selected' : '' }}>Laravel</option>
                    <option value="nginx-access" {{ $logType === 'nginx-access' ? 'selected' : '' }}>Nginx Access</option>
                    <option value="nginx-error" {{ $logType === 'nginx-error' ? 'selected' : '' }}>Nginx Error</option>
                    <option value="php-fpm" {{ $logType === 'php-fpm' ? 'selected' : '' }}>PHP-FPM</option>
                    <option value="system" {{ $logType === 'system' ? 'selected' : '' }}>System (syslog)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Search logs...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Actions -->
<div class="mb-3">
    @if($logType === 'laravel')
        <form id="clear-laravel-log-form" action="{{ route('logs.clear') }}" method="POST" class="d-inline">
            @csrf
            <button type="button" class="btn btn-danger" onclick="confirmDelete('Clear Laravel log? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('clear-laravel-log-form').submit(); })">
                <i class="bi bi-trash"></i> Clear Laravel Log
            </button>
        </form>
    @endif
</div>

<!-- Logs Display -->
<div class="card">
    <div class="card-header">
        <h5>{{ ucfirst(str_replace('-', ' ', $logType)) }} Logs (Last 500 lines)</h5>
    </div>
    <div class="card-body p-0">
        @if(empty($logs))
            <div class="p-3 text-muted">No logs found.</div>
        @else
            <div style="max-height: 600px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4;">
                <pre class="p-3 mb-0" style="font-size: 12px; line-height: 1.5;">@foreach($logs as $line){{ $line }}
@endforeach</pre>
            </div>
        @endif
    </div>
    <div class="card-footer text-muted small">
        Showing latest {{ count($logs) }} lines {{ $search ? '(filtered)' : '' }}
    </div>
</div>
@endsection
