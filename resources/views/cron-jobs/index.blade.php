@extends('layouts.app')

@section('title', 'Cron Jobs - Git Webhook Manager')
@section('page-title', 'Cron Jobs')
@section('page-description', 'Schedule and manage automated tasks')

@section('page-actions')
    <a href="{{ route('cron-jobs.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create Cron Job
    </a>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @if($cronJobs->isEmpty())
            <p class="text-muted">No cron jobs configured.</p>
        @else
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Command</th>
                            <th>Schedule</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Last Run</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cronJobs as $job)
                            <tr>
                                <td>{{ $job->name }}</td>
                                <td><code>{{ Str::limit($job->command, 40) }}</code></td>
                                <td><code>{{ $job->schedule }}</code></td>
                                <td>{{ $job->user }}</td>
                                <td>
                                    <form action="{{ route('cron-jobs.toggle', $job) }}" method="POST" class="d-inline">
                                        @csrf
                                        @if($job->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </form>
                                </td>
                                <td>
                                    @if($job->last_run_at)
                                        {{ $job->last_run_at->diffForHumans() }}
                                    @else
                                        <span class="text-muted">Never</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('cron-jobs.edit', $job) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form id="delete-cron-job-{{ $job->id }}" action="{{ route('cron-jobs.destroy', $job) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete('Delete this cron job? This action cannot be undone!').then(confirmed => { if(confirmed) document.getElementById('delete-cron-job-{{ $job->id }}').submit(); })">
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
@endsection
