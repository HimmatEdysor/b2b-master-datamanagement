@extends('layouts.admin')
@section('title', 'Roles')
@section('page-title', 'Roles & permissions')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Assign module access like B2B CRM. Super Admin is protected; other roles can be edited.</p>
    @if(master_can('roles.create'))
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">Add role</a>
    @endif
</div>

<div class="card table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Role</th>
                <th>Users</th>
                @if(master_can('roles.edit'))
                    <th>Status</th>
                @endif
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach($roles as $role)
            <tr>
                <td>
                    <span class="cell-primary">{{ $role->name }}</span>
                    <code class="code-pill">{{ $role->slug }}</code>
                </td>
                <td>{{ $role->users_count }}</td>
                @if(master_can('roles.edit'))
                    <td id="role-status-{{ $role->id }}">
                        @if($role->isProtected())
                            <span class="cell-muted">Always on</span>
                        @else
                            <label class="switch-toggle">
                                <input type="checkbox"
                                    onchange="toggleRoleStatus({{ $role->id }})"
                                    {{ $role->is_active ? 'checked' : '' }}>
                                <span class="switch-slider"></span>
                            </label>
                        @endif
                    </td>
                @endif
                <td>
                    @if(master_can('roles.edit') && ! $role->isProtected())
                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-outline btn-sm">Edit</a>
                    @elseif($role->isProtected())
                        <span class="cell-muted">Protected</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
function toggleRoleStatus(roleId) {
    fetch(`{{ url('admin/roles') }}/${roleId}/change-status`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert(data.message || 'Could not update status');
            location.reload();
        }
    })
    .catch(() => {
        alert('Could not update status');
        location.reload();
    });
}
</script>
@endpush
