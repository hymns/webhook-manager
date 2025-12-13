@extends('layouts.app')

@section('title', 'Logs - Git Webhook Manager')
@section('page-title', 'Log Viewer')
@section('page-description', 'View and search application, system, and website-specific logs')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(str_starts_with($logType, 'website-') && !$website)
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Website Required:</strong> Please select a website from the dropdown to view website-specific logs.
    </div>
@endif

<!-- Log Type Selector -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3" id="logForm">
            <div class="col-md-3">
                <label class="form-label">Log Type</label>
                <select name="type" class="form-select" id="logTypeSelect">
                    <optgroup label="Application Logs">
                        <option value="laravel" {{ $logType === 'laravel' ? 'selected' : '' }}>Laravel</option>
                        <option value="queue" {{ $logType === 'queue' ? 'selected' : '' }}>Queue Worker</option>
                        <option value="scheduler" {{ $logType === 'scheduler' ? 'selected' : '' }}>Scheduler</option>
                    </optgroup>
                    <optgroup label="System Logs">
                        <option value="nginx-access" {{ $logType === 'nginx-access' ? 'selected' : '' }}>Nginx Access (All)</option>
                        <option value="nginx-error" {{ $logType === 'nginx-error' ? 'selected' : '' }}>Nginx Error (All)</option>
                        <option value="php-fpm" {{ $logType === 'php-fpm' ? 'selected' : '' }}>PHP-FPM (All)</option>
                        <option value="system" {{ $logType === 'system' ? 'selected' : '' }}>System (syslog)</option>
                    </optgroup>
                    <optgroup label="Website-Specific" id="websiteLogsGroup">
                        <option value="website-nginx-access" {{ $logType === 'website-nginx-access' ? 'selected' : '' }}>→ Nginx Access</option>
                        <option value="website-nginx-error" {{ $logType === 'website-nginx-error' ? 'selected' : '' }}>→ Nginx Error</option>
                        <option value="website-php-access" {{ $logType === 'website-php-access' ? 'selected' : '' }}>→ PHP-FPM Access</option>
                        <option value="website-php-slow" {{ $logType === 'website-php-slow' ? 'selected' : '' }}>→ PHP-FPM Slow Queries</option>
                        <option value="website-laravel" {{ $logType === 'website-laravel' ? 'selected' : '' }}>→ Laravel Log (PHP only)</option>
                    </optgroup>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Website <small class="text-muted">(for website-specific logs)</small></label>
                <select name="website_id" class="form-select" id="websiteSelect" onchange="this.form.submit()">
                    <option value="">Select Website...</option>
                    @foreach($websites as $site)
                        <option value="{{ $site->id }}" {{ $website && $website->id === $site->id ? 'selected' : '' }}>
                            {{ $site->name }} ({{ $site->domain }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
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
    @if(in_array($logType, ['laravel', 'queue', 'scheduler']))
        <form id="clear-log-form" action="{{ route('logs.clear') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="type" value="{{ $logType }}">
            <button type="button" class="btn btn-danger" onclick="confirmDelete('Clear {{ ucfirst($logType) }} log? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('clear-log-form').submit(); })">
                <i class="bi bi-trash"></i> Clear {{ ucfirst($logType) }} Log
            </button>
        </form>
    @endif
</div>

<!-- Logs Display -->
<div class="card">
    <div class="card-header">
        <h5>
            {{ ucfirst(str_replace('-', ' ', $logType)) }} Logs 
            @if($website)
                <span class="badge bg-primary">{{ $website->name }} ({{ $website->domain }})</span>
            @endif
            <small class="text-muted">(Last 500 lines)</small>
        </h5>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const logTypeSelect = document.getElementById('logTypeSelect');
    const websiteSelect = document.getElementById('websiteSelect');
    const websiteLogsGroup = document.getElementById('websiteLogsGroup');
    
    function checkWebsiteSpecificLog() {
        const selectedType = logTypeSelect.value;
        const isWebsiteSpecific = selectedType.startsWith('website-');
        
        // Highlight website selector if website-specific log is selected
        if (isWebsiteSpecific) {
            websiteSelect.classList.add('border-warning');
            if (!websiteSelect.value) {
                // Show info that website must be selected
                websiteSelect.parentElement.classList.add('was-validated');
            }
        } else {
            websiteSelect.classList.remove('border-warning');
            websiteSelect.parentElement.classList.remove('was-validated');
        }
    }
    
    // Check on page load
    checkWebsiteSpecificLog();
    
    // Auto-submit when log type changes (if not website-specific or website is already selected)
    logTypeSelect.addEventListener('change', function() {
        checkWebsiteSpecificLog();
        
        const isWebsiteSpecific = this.value.startsWith('website-');
        if (!isWebsiteSpecific || websiteSelect.value) {
            document.getElementById('logForm').submit();
        }
    });
});
</script>
@endsection
