@extends('layouts.app')

@section('title', $webhook->name . ' - Git Webhook Manager')
@section('page-title', $webhook->name)
@section('page-description', $webhook->domain ?? 'Webhook Details')

@section('page-actions')
    <a href="{{ route('webhooks.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to List
    </a>
@endsection

@section('content')
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Webhook Details -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i> Webhook Details
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Status:</strong></div>
                        <div class="col-md-9">
                            <form action="{{ route('webhooks.toggle', $webhook) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="badge bg-{{ $webhook->status_badge }} border-0" style="cursor: pointer;">
                                    {{ $webhook->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Git Provider:</strong></div>
                        <div class="col-md-9">
                            <i class="bi {{ $webhook->provider_icon }} me-1"></i>
                            {{ ucfirst($webhook->git_provider) }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Repository:</strong></div>
                        <div class="col-md-9"><code>{{ $webhook->repository_url }}</code></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Branch:</strong></div>
                        <div class="col-md-9"><code>{{ $webhook->branch }}</code></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Local Path:</strong></div>
                        <div class="col-md-9"><code>{{ $webhook->local_path }}</code></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Deploy User:</strong></div>
                        <div class="col-md-9">
                            <code>{{ $webhook->deploy_user ?? 'www-data' }}</code>
                            <small class="text-muted ms-2">
                                <i class="bi bi-info-circle"></i> Commands executed as this user
                            </small>
                        </div>
                    </div>

                    @if($webhook->last_deployed_at)
                        <div class="row mb-3">
                            <div class="col-md-3"><strong>Last Deployed:</strong></div>
                            <div class="col-md-9">{{ $webhook->last_deployed_at->format('d M Y, h:i A') }} ({{ $webhook->last_deployed_at->diffForHumans() }})</div>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-3"><strong>Created:</strong></div>
                        <div class="col-md-9">{{ $webhook->created_at->format('d M Y, h:i A') }}</div>
                    </div>
                </div>
            </div>

            <!-- Webhook URL -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-link-45deg me-2"></i> Webhook URL
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Configure this URL in your Git provider's webhook settings:</p>
                    <div class="code-block">
                        <pre class="mb-0">{{ $webhook->webhook_url }}</pre>
                        <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyToClipboard('{{ $webhook->webhook_url }}', this)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>{{ ucfirst($webhook->git_provider) }} Setup:</strong>
                        @if($webhook->git_provider === 'github')
                            <ol class="mb-0 mt-2 small">
                                <li>Go to your repository → Settings → Webhooks → Add webhook</li>
                                <li>Paste the Payload URL above</li>
                                <li>Set Content type to <code>application/json</code></li>
                                <li>Set Secret to your webhook secret token (see below)</li>
                                <li>Select "Just the push event"</li>
                                <li>Make sure "Active" is checked</li>
                            </ol>
                        @else
                            <ol class="mb-0 mt-2 small">
                                <li>Go to your repository → Settings → Webhooks → Add webhook</li>
                                <li>Paste the URL above</li>
                                <li>Set Secret Token (see below)</li>
                                <li>Trigger: Push events</li>
                                <li>Make sure "Enable SSL verification" is checked</li>
                            </ol>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Secret Token -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-lock me-2"></i> Secret Token
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Use this secret token to secure your webhook:</p>
                    <div class="code-block">
                        <pre class="mb-0">{{ $webhook->secret_token }}</pre>
                        <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyToClipboard('{{ $webhook->secret_token }}', this)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Keep this token secure! Anyone with this token can trigger deployments.
                    </div>
                </div>
            </div>

            <!-- SSH Key -->
            @if($webhook->sshKey)
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-key me-2"></i> SSH Key</span>
                        <form id="regenerate-ssh-form" action="{{ route('webhooks.generate-ssh-key', $webhook) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="confirmAction('Regenerate SSH Key?', 'You will need to update the key in your Git provider.', 'Yes, regenerate!', 'warning').then(confirmed => { if(confirmed) document.getElementById('regenerate-ssh-form').submit(); })">
                                <i class="bi bi-arrow-clockwise me-1"></i> Regenerate
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-2">Add this public key to your Git provider's deploy keys:</p>
                        <div class="code-block">
                            <pre class="mb-0">{{ $webhook->sshKey->public_key }}</pre>
                            <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyToClipboard('{{ addslashes($webhook->sshKey->public_key) }}', this)">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        @if($webhook->sshKey->fingerprint)
                            <div class="mt-3">
                                <strong>Fingerprint:</strong>
                                <code class="ms-2">{{ $webhook->sshKey->fingerprint }}</code>
                            </div>
                        @endif
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Adding Deploy Key:</strong>
                            @if($webhook->git_provider === 'github')
                                <p class="mb-0 mt-2 small">Go to Repository → Settings → Deploy keys → Add deploy key. Paste the public key above. You don't need write access for deployments.</p>
                            @else
                                <p class="mb-0 mt-2 small">Go to Repository → Settings → Repository → Deploy Keys → Add key. Paste the public key above.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-key me-2"></i> SSH Key
                    </div>
                    <div class="card-body text-center py-4">
                        <p class="text-muted">No SSH key generated yet.</p>
                        <form action="{{ route('webhooks.generate-ssh-key', $webhook) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Generate SSH Key
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Recent Deployments -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-clock-history me-2"></i> Recent Deployments
                </div>
                <div class="card-body p-0">
                    @if($webhook->deployments->isEmpty())
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">No deployments yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Commit</th>
                                        <th>Message</th>
                                        <th>Author</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($webhook->deployments as $deployment)
                                        <tr>
                                            <td>
                                                <span class="badge bg-{{ $deployment->status_badge }}">
                                                    <i class="bi {{ $deployment->status_icon }} me-1"></i>
                                                    {{ ucfirst($deployment->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($deployment->commit_hash)
                                                    <code>{{ $deployment->short_commit_hash }}</code>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($deployment->commit_message)
                                                    {{ Str::limit($deployment->commit_message, 40) }}
                                                @else
                                                    <span class="text-muted">Manual deployment</span>
                                                @endif
                                            </td>
                                            <td>{{ $deployment->author ?? '-' }}</td>
                                            <td>
                                                <small>{{ $deployment->created_at->diffForHumans() }}</small>
                                            </td>
                                            <td>
                                                <a href="{{ route('deployments.show', $deployment) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-search"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-lightning me-2"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <form action="{{ route('deployments.trigger', $webhook) }}" method="POST" class="d-grid">
                            @csrf
                            <button type="submit" class="btn btn-success" {{ !$webhook->is_active ? 'disabled' : '' }}>
                                <i class="bi bi-rocket-takeoff-fill me-2"></i> Manual Deployment
                            </button>
                        </form>

                        <form action="{{ route('webhooks.toggle', $webhook) }}" method="POST" class="d-grid">
                            @csrf
                            <button type="submit" class="btn btn-{{ $webhook->is_active ? 'warning' : 'success' }}">
                                <i class="bi bi-{{ $webhook->is_active ? 'pause' : 'play' }}-circle me-2"></i>
                                {{ $webhook->is_active ? 'Disable' : 'Enable' }} Webhook
                            </button>
                        </form>
                        
                        <a href="{{ route('webhooks.edit', $webhook) }}" class="btn btn-primary">
                            <i class="bi bi-pencil me-2"></i> Edit Configuration
                        </a>

                        <form action="{{ route('webhooks.destroy', $webhook) }}" method="POST" class="d-grid" onsubmit="return confirmDelete('Are you sure you want to delete this webhook and all its deployments?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash me-2"></i> Delete Webhook
                            </button>
                        </form>

                        @if(!$webhook->sshKey)
                            <form action="{{ route('webhooks.generate-ssh-key', $webhook) }}" method="POST" class="d-grid">
                                @csrf
                                <button type="submit" class="btn btn-info">
                                    <i class="bi bi-key me-2"></i> Generate SSH Key
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Deploy Scripts -->
            @if($webhook->pre_deploy_script || $webhook->post_deploy_script)
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-terminal me-2"></i> Deploy Scripts
                    </div>
                    <div class="card-body">
                        @if($webhook->pre_deploy_script)
                            <h6 class="mb-2">Pre-Deploy:</h6>
                            <pre class="small mb-3">{{ $webhook->pre_deploy_script }}</pre>
                        @endif

                        @if($webhook->post_deploy_script)
                            <h6 class="mb-2">Post-Deploy:</h6>
                            <pre class="small mb-0">{{ $webhook->post_deploy_script }}</pre>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
