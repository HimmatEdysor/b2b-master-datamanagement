@extends('layouts.admin')
@section('title', 'Permissions')
@section('page-title', 'Permissions')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Master portal permission keys (synced from config, assignable on roles).</p>
    <div class="toolbar-actions">
        @if(master_can('permissions.edit'))
            <form method="POST" action="{{ route('admin.permissions.sync-config') }}" class="inline-form">
                @csrf
                <button type="submit" class="btn btn-outline">Sync from config</button>
            </form>
        @endif
        @if(master_can('permissions.create'))
            <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">Add permission</a>
        @endif
    </div>
</div>

<div class="card table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Group</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($permissions as $permission)
            <tr>
                <td><code>{{ $permission->name }}</code></td>
                <td>{{ $permission->group ?? '—' }}</td>
                <td>{{ $permission->is_active ? 'Active' : 'Inactive' }}</td>
                <td>
                    @if(master_can('permissions.edit'))
                        <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-outline btn-sm">Edit</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="cell-muted">No permissions yet. Click “Sync from config”.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $permissions->links() }}
@endsection
