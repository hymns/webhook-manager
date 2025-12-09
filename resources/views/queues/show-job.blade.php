@extends('layouts.app')

@section('title', 'Job Details - Queue Management')
@section('page-title', 'Job Details')
@section('page-description', 'Pending job #' . $job['id'])

@section('page-actions')
    <div class="d-flex gap-2">
        <form id="delete-job-detail-form" action="{{ route('queues.delete-job', $job['id']) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="button" class="btn btn-danger" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-job-detail-form').submit(); })">
                <i class="bi bi-trash me-1"></i> Delete Job
            </button>
        </form>
        <a href="{{ route('queues.pending') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Pending
        </a>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Job Information
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td width="200" class="text-muted">Job ID:</td>
                                <td><code>{{ $job['id'] }}</code></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Job Name:</td>
                                <td><strong>{{ $job['display_name'] }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Queue:</td>
                                <td><span class="badge bg-info">{{ $job['queue'] }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Attempts:</td>
                                <td>{{ $job['attempts'] }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Available At:</td>
                                <td>{{ $job['available_at'] }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Created At:</td>
                                <td>{{ $job['created_at'] }}</td>
                            </tr>
                            @if($job['reserved_at'])
                            <tr>
                                <td class="text-muted">Reserved At:</td>
                                <td>{{ $job['reserved_at'] }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-code-square me-2"></i>Job Payload
                    </h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;"><code>{{ json_encode($job['payload'], JSON_PRETTY_PRINT) }}</code></pre>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <form id="delete-job-sidebar-form" action="{{ route('queues.delete-job', $job['id']) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger w-100" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-job-sidebar-form').submit(); })">
                                <i class="bi bi-trash me-2"></i>Delete Job
                            </button>
                        </form>
                        <a href="{{ route('queues.pending') }}" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Pending Jobs
                        </a>
                        <a href="{{ route('queues.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
