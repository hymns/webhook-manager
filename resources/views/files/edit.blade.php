@extends('layouts.app')

@section('title', 'Edit File - Git Webhook Manager')
@section('page-title', basename($path))
@section('page-description', dirname($path))

@section('page-actions')
    <a href="{{ route('files.index', ['path' => dirname($path)]) }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
    <a href="{{ route('files.download', ['path' => $path]) }}" class="btn btn-info">
        <i class="bi bi-download me-1"></i> Download
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-9">
        <form action="{{ route('files.update') }}" method="POST" id="editorForm">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">File Editor</h5>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
                <div class="card-body p-0">
                    <textarea name="content" 
                              id="fileContent" 
                              class="form-control border-0 font-monospace" 
                              style="min-height: 600px; font-size: 14px; line-height: 1.5; tab-size: 4;"
                              spellcheck="false">{{ $content }}</textarea>
                </div>
                <div class="card-footer bg-white">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Press Ctrl+S to save
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                Lines: <span id="lineCount">0</span> | 
                                Characters: <span id="charCount">0</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-3">
        <!-- File Info -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="card-title mb-0">File Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Size:</td>
                        <td class="text-end">
                            @php
                                $units = ['B', 'KB', 'MB', 'GB'];
                                $size = $fileInfo['size'];
                                $i = 0;
                                while ($size > 1024 && $i < count($units) - 1) {
                                    $size /= 1024;
                                    $i++;
                                }
                            @endphp
                            {{ round($size, 2) }} {{ $units[$i] }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Permissions:</td>
                        <td class="text-end"><code>{{ $fileInfo['permissions'] }}</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Owner:</td>
                        <td class="text-end">{{ $fileInfo['owner'] }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Group:</td>
                        <td class="text-end">{{ $fileInfo['group'] }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Modified:</td>
                        <td class="text-end"><small>{{ $fileInfo['modified'] }}</small></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="card-title mb-0">Quick Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('editorForm').submit()">
                    <i class="bi bi-save"></i> Save File
                </button>
                <a href="{{ route('files.download', ['path' => $path]) }}" 
                   class="btn btn-outline-success btn-sm">
                    <i class="bi bi-download"></i> Download
                </a>
                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#renameModal">
                    <i class="bi bi-input-cursor-text"></i> Rename
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#chmodModal">
                    <i class="bi bi-shield-lock"></i> Permissions
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteFile()">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('files.rename') }}" method="POST">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rename File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New Name</label>
                        <input type="text" name="new_name" class="form-control" value="{{ basename($path) }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Chmod Modal -->
<div class="modal fade" id="chmodModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('files.chmod') }}" method="POST">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Permissions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Permissions (Octal)</label>
                        <input type="text" name="permissions" class="form-control" value="{{ $fileInfo['permissions'] }}" required pattern="[0-7]{3,4}">
                        <small class="text-muted">Examples: 644 (rw-r--r--), 755 (rwxr-xr-x)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Change</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Keyboard shortcut for save
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('editorForm').submit();
    }
});

// Update character and line count
const textarea = document.getElementById('fileContent');
function updateCounts() {
    document.getElementById('charCount').textContent = textarea.value.length;
    document.getElementById('lineCount').textContent = textarea.value.split('\n').length;
}
textarea.addEventListener('input', updateCounts);
updateCounts();

// Tab key support
textarea.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 4;
    }
});

async function deleteFile() {
    const confirmed = await confirmDelete('Are you sure you want to delete this file? This action cannot be undone!');
    
    if (confirmed) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('files.delete') }}';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        
        const pathInput = document.createElement('input');
        pathInput.type = 'hidden';
        pathInput.name = 'path';
        pathInput.value = '{{ $path }}';
        
        form.appendChild(csrfInput);
        form.appendChild(pathInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endsection
