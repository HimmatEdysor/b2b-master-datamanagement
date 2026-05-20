@extends('layouts.admin')
@section('title', 'Admin users')
@section('page-title', 'Users')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Master portal staff accounts and role assignments (same pattern as B2B CRM users).</p>
    @if(master_can('users.create'))
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add user</a>
    @endif
</div>

<div class="card table-card">
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Roles</th><th></th></tr>
        </thead>
        <tbody>
        @foreach($users as $user)
            @php
                $isLastSuperAdmin = $user->isSuperAdmin() && \App\Models\User::query()
                    ->whereHas('roles', fn ($q) => $q->where('slug', config('master_permissions.super_admin_role')))
                    ->count() <= 1;
            @endphp
            <tr>
                <td>
                    {{ $user->name }}
                    @if($user->isSuperAdmin())
                        <span class="badge badge-published" style="margin-left:6px">Super Admin</span>
                    @endif
                </td>
                <td>{{ $user->email }}</td>
                <td>
                    @forelse($user->roles as $role)
                        <code class="code-pill">{{ $role->name }}</code>
                    @empty
                        <span class="cell-muted">—</span>
                    @endforelse
                </td>
                <td class="table-actions">
                    @if(master_can('users.edit'))
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline btn-sm">Edit</a>
                    @endif
                    @if(master_can('users.delete') && $user->id !== auth()->id() && ! $isLastSuperAdmin)
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline-form"
                              onsubmit="return confirm('Delete this user?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $users->links() }}
@endsection
