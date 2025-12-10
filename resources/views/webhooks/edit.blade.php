@extends('layouts.app')

@section('title', 'Edit Webhook - Git Webhook Manager')
@section('page-title', 'Edit Webhook')
@section('page-description', 'Update webhook configuration')

@section('page-actions')
    <a href="{{ route('webhooks.show', $webhook) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Details
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('webhooks.update', $webhook) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i> Basic Information
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Webhook Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $webhook->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="domain" class="form-label">Domain / Website Reference</label>
                            <input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain" value="{{ old('domain', $webhook->domain) }}" placeholder="example.com">
                            @error('domain')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $webhook->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-git me-2"></i> Repository Configuration
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="git_provider" class="form-label">Git Provider <span class="text-danger">*</span></label>
                            <select class="form-select @error('git_provider') is-invalid @enderror" id="git_provider" name="git_provider" required>
                                <option value="github" {{ old('git_provider', $webhook->git_provider) == 'github' ? 'selected' : '' }}>GitHub</option>
                                <option value="gitlab" {{ old('git_provider', $webhook->git_provider) == 'gitlab' ? 'selected' : '' }}>GitLab</option>
                            </select>
                            @error('git_provider')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="repository_url" class="form-label">Repository URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('repository_url') is-invalid @enderror" id="repository_url" name="repository_url" value="{{ old('repository_url', $webhook->repository_url) }}" required>
                            @error('repository_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="branch" class="form-label">Branch <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('branch') is-invalid @enderror" id="branch" name="branch" value="{{ old('branch', $webhook->branch) }}" required>
                            @error('branch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="local_path" class="form-label">Local Path <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('local_path') is-invalid @enderror" id="local_path" name="local_path" value="{{ old('local_path', $webhook->local_path) }}" required>
                            @error('local_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="deploy_user" class="form-label">Deploy User</label>
                            <input type="text" class="form-control @error('deploy_user') is-invalid @enderror" id="deploy_user" name="deploy_user" value="{{ old('deploy_user', $webhook->deploy_user ?? 'www-data') }}" placeholder="www-data">
                            <div class="form-text">
                                User that will execute deployment commands. Ensure this user has permissions for the deployment path.
                                <br>Common users: <code>www-data</code>, <code>www</code>, <code>nginx</code>, <code>apache</code>, <code>http</code>
                            </div>
                            @error('deploy_user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-terminal me-2"></i> Deploy Scripts (Optional)
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="pre_deploy_script" class="form-label">Pre-Deploy Script</label>
                            <textarea class="form-control font-monospace @error('pre_deploy_script') is-invalid @enderror" id="pre_deploy_script" name="pre_deploy_script" rows="4">{{ old('pre_deploy_script', $webhook->pre_deploy_script) }}</textarea>
                            @error('pre_deploy_script')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="post_deploy_script" class="form-label">Post-Deploy Script</label>
                            <textarea class="form-control font-monospace @error('post_deploy_script') is-invalid @enderror" id="post_deploy_script" name="post_deploy_script" rows="4">{{ old('post_deploy_script', $webhook->post_deploy_script) }}</textarea>
                            @error('post_deploy_script')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Update Webhook
                    </button>
                    <a href="{{ route('webhooks.show', $webhook) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-header">
                    <i class="bi bi-lightbulb me-2"></i> Quick Tips
                </div>
                <div class="card-body">
                    <h6>Configuration Changes</h6>
                    <p class="small">Updating webhook settings does not affect existing deployments. Changes apply to the next deployment trigger.</p>
                    
                    <h6 class="mt-3">Repository URL</h6>
                    <p class="small">Changing the repository URL will require updating the SSH key in your new Git provider if using SSH authentication.</p>

                    <h6 class="mt-3">Branch Changes</h6>
                    <p class="small">After changing the branch, the webhook will deploy from the new branch on the next trigger.</p>

                    <h6 class="mt-3">Local Path</h6>
                    <p class="small">Changing local path requires manual file system changes. Make sure the new path exists and has proper permissions.</p>

                    <h6 class="mt-3">Deploy Scripts</h6>
                    <p class="small">Test your deploy scripts carefully. Errors in scripts can cause deployments to fail. Check deployment logs for debugging.</p>

                    <h6 class="mt-3">PHP Paths by Version</h6>
                    <p class="small">Use specific PHP version in deploy scripts:</p>
                    <ul class="small mb-0" style="font-family: monospace; font-size: 0.8rem;">
                        <li>/usr/bin/php7.4</li>
                        <li>/usr/bin/php8.0</li>
                        <li>/usr/bin/php8.1</li>
                        <li>/usr/bin/php8.2</li>
                        <li>/usr/bin/php8.3</li>
                        <li>/usr/bin/php8.4</li>
                    </ul>
                    <p class="small mt-2 mb-0">Laravel Example:</p>
                    <code class="small d-block bg-white p-2 rounded mb-2">/usr/bin/php8.3 artisan migrate</code>
                    <p class="small mt-2 mb-0">Composer Example:</p>
                    <code class="small d-block bg-white p-2 rounded">/usr/bin/php8.3 /usr/bin/composer install</code>

                    <h6 class="mt-3">SSH Key</h6>
                    <p class="small">The SSH key remains the same when editing. To regenerate, delete and recreate the webhook.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
