@extends('layouts.app')

@section('title', 'File Manager - Git Webhook Manager')
@section('page-title', 'File Manager')
@section('page-description', 'Browse and manage server files')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-upload me-1"></i> Upload
    </button>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createFileModal">
        <i class="bi bi-file-plus me-1"></i> New File
    </button>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createDirModal">
        <i class="bi bi-folder-plus me-1"></i> New Folder
    </button>
@endsection

@section('content')
<!-- Path breadcrumb -->
<div class="card mb-3">
    <div class="card-body py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('files.index', ['path' => '/']) }}" class="text-decoration-none">
                        <i class="bi bi-hdd"></i> Root
                    </a>
                </li>
                @php
                    $pathParts = explode('/', trim($path, '/'));
                    $currentPath = '';
                @endphp
                @foreach($pathParts as $part)
                    @if($part)
                        @php $currentPath .= '/' . $part; @endphp
                        <li class="breadcrumb-item">
                            <a href="{{ route('files.index', ['path' => $currentPath]) }}" class="text-decoration-none">
                                {{ $part }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ol>
        </nav>
    </div>
</div>

<!-- File listing -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 file-manager-table">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 50%">Name</th>
                        <th style="width: 15%">Size</th>
                        <th style="width: 20%">Modified</th>
                        <th style="width: 15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Parent directory -->
                    @if($path !== '/' && $path !== '')
                        <tr>
                            <td colspan="4">
                                <a href="{{ route('files.index', ['path' => $parentPath]) }}" class="text-decoration-none">
                                    <i class="bi bi-arrow-up"></i> .. (Parent Directory)
                                </a>
                            </td>
                        </tr>
                    @endif

                    @forelse($items as $item)
                        <tr>
                            <td>
                                @if($item['type'] === 'directory')
                                    <a href="{{ route('files.index', ['path' => $item['path']]) }}" class="text-decoration-none">
                                        <i class="bi bi-folder-fill text-warning me-2"></i>
                                        <span class="file-manager-folder-name">{{ $item['name'] }}</span>
                                    </a>
                                @else
                                    <i class="bi bi-file-earmark text-secondary me-2"></i>
                                    <span class="file-manager-file-name">{{ $item['name'] }}</span>
                                @endif
                                <span class="file-manager-permissions ms-2">{{ $item['permissions'] }}</span>
                            </td>
                            <td class="file-manager-size">
                                @if($item['type'] === 'file')
                                    {{ $item['size'] }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="file-manager-modified">
                                {{ $item['modified'] }}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($item['type'] === 'file')
                                        <a href="{{ route('files.edit', ['path' => $item['path']]) }}" 
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ route('files.download', ['path' => $item['path']]) }}" 
                                           class="btn btn-outline-secondary" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endif
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="renameItem('{{ $item['path'] }}', '{{ $item['name'] }}')" 
                                            title="Rename">
                                        <i class="bi bi-input-cursor-text"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="chmodItem('{{ $item['path'] }}', '{{ substr($item['permissions'], 1) }}')" 
                                            title="Permissions">
                                        <i class="bi bi-shield-lock"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="deleteItem('{{ $item['path'] }}', '{{ $item['type'] }}')" 
                                            title="Delete">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2 mb-0">This directory is empty</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('files.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" name="file" class="form-control" required>
                        <small class="text-muted">Max size: 100MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Create File Modal -->
<div class="modal fade" id="createFileModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('files.create-file') }}" method="POST">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">File Name</label>
                        <input type="text" name="name" class="form-control" placeholder="example.txt" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Create Directory Modal -->
<div class="modal fade" id="createDirModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('files.create-directory') }}" method="POST">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Folder Name</label>
                        <input type="text" name="name" class="form-control" placeholder="my-folder" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Create</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('files.rename') }}" method="POST" id="renameForm">
            @csrf
            <input type="hidden" name="path" id="renamePath">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rename</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New Name</label>
                        <input type="text" name="new_name" id="renameNewName" class="form-control" required>
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
        <form action="{{ route('files.chmod') }}" method="POST" id="chmodForm">
            @csrf
            <input type="hidden" name="path" id="chmodPath">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Permissions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Permissions (Octal)</label>
                        <input type="text" name="permissions" id="chmodPerms" class="form-control" placeholder="644" required pattern="[0-7]{3,4}">
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
function renameItem(path, name) {
    document.getElementById('renamePath').value = path;
    document.getElementById('renameNewName').value = name;
    new bootstrap.Modal(document.getElementById('renameModal')).show();
}

function chmodItem(path, perms) {
    document.getElementById('chmodPath').value = path;
    document.getElementById('chmodPerms').value = '';
    new bootstrap.Modal(document.getElementById('chmodModal')).show();
}

async function deleteItem(path, type) {
    const confirmed = await confirmDelete(`Delete this ${type}? This action cannot be undone!`);
    
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
        pathInput.value = path;
        
        const recursiveInput = document.createElement('input');
        recursiveInput.type = 'hidden';
        recursiveInput.name = 'recursive';
        recursiveInput.value = type === 'directory' ? '1' : '0';
        
        form.appendChild(csrfInput);
        form.appendChild(pathInput);
        form.appendChild(recursiveInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endsection
