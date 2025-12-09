@extends('layouts.app')

@section('title', 'Failed Job Details - Queue Management')
@section('page-title', 'Failed Job Details')
@section('page-description', 'Job UUID: ' . substr($job['uuid'], 0, 8) . '...')

@section('page-actions')
    <div class="d-flex gap-2">
        <form action="{{ route('queues.retry-failed-job', $job['uuid']) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-arrow-clockwise me-1"></i> Retry Job
            </button>
        </form>
        <form id="delete-failed-job-detail-form" action="{{ route('queues.delete-failed-job', $job['uuid']) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="button" class="btn btn-danger" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-failed-job-detail-form').submit(); })">
                <i class="bi bi-trash me-1"></i> Delete
            </button>
        </form>
        <a href="{{ route('queues.failed') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
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
                                <td class="text-muted">UUID:</td>
                                <td><code class="small">{{ $job['uuid'] }}</code></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Job Name:</td>
                                <td><strong>{{ $job['display_name'] }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Connection:</td>
                                <td>{{ $job['connection'] }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Queue:</td>
                                <td><span class="badge bg-danger">{{ $job['queue'] }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Failed At:</td>
                                <td>{{ $job['failed_at'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0 text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Exception Details
                    </h5>
                </div>
                <div class="card-body">
                    <pre class="bg-danger bg-opacity-10 p-3 rounded text-danger" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap;"><code>{{ $job['exception'] }}</code></pre>
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
                        <form action="{{ route('queues.retry-failed-job', $job['uuid']) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-arrow-clockwise me-2"></i>Retry This Job
                            </button>
                        </form>
                        <form id="delete-failed-job-sidebar-form" action="{{ route('queues.delete-failed-job', $job['uuid']) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger w-100" onclick="confirmDelete('Delete this job?').then(confirmed => { if(confirmed) document.getElementById('delete-failed-job-sidebar-form').submit(); })">
                                <i class="bi bi-trash me-2"></i>Delete Job
                            </button>
                        </form>
                        <hr>
                        <a href="{{ route('queues.failed') }}" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Failed Jobs
                        </a>
                        <a href="{{ route('queues.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>About Retrying
                    </h6>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        Retrying will push this job back to the queue for processing. 
                        Make sure the underlying issue has been fixed before retrying.
                    </small>
                </div>
            </div>
        </div>
    </div>
@endsection
