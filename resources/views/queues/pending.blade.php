@extends('layouts.app')

@section('title', 'Pending Jobs - Queue Management')
@section('page-title', 'Pending Jobs')
@section('page-description', 'All jobs waiting to be processed')

@section('page-actions')
    <a href="{{ route('queues.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            @if(empty($jobs))
                <div class="text-center py-5">
                    <i class="bi bi-hourglass-split text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-4">No pending jobs</h4>
                    <p class="text-muted">Queue is empty - all jobs have been processed!</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Job Name</th>
                                <th>Queue</th>
                                <th>Attempts</th>
                                <th>Available At</th>
                                <th>Created At</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobs as $job)
                                <tr>
                                    <td><code>{{ $job['id'] }}</code></td>
                                    <td>
                                        <strong>{{ $job['display_name'] }}</strong>
                                    </td>
                                    <td><span class="badge bg-info">{{ $job['queue'] }}</span></td>
                                    <td>
                                        @if($job['attempts'] > 0)
                                            <span class="badge bg-warning">{{ $job['attempts'] }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $job['attempts'] }}</span>
                                        @endif
                                    </td>
                                    <td><small class="text-muted">{{ $job['available_at'] }}</small></td>
                                    <td><small class="text-muted">{{ $job['created_at'] }}</small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('queues.show-job', $job['id']) }}" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-search"></i>
                                            </a>
                                            @if(config('queue.default') !== 'redis')
                                                <form id="delete-pending-job-form-{{ $job['id'] }}" action="{{ route('queues.delete-job', $job['id']) }}" method="POST" style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-outline-danger" title="Delete" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-pending-job-form-{{ $job['id'] }}').submit(); })">
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

                <div class="mt-3">
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Showing {{ count($jobs) }} pending job(s)
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection
