@extends('layouts.admin')
@section('title', 'Edit user')
@section('page-title', 'Edit user')

@section('content')
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="card" style="padding:20px;max-width:520px">
    @csrf
    @method('PUT')
    @include('admin.users._form')
    <div style="display:flex;gap:12px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Save</button>
        @php
            $isLastSuperAdmin = $user->isSuperAdmin() && \App\Models\User::query()
                ->whereHas('roles', fn ($q) => $q->where('slug', config('master_permissions.super_admin_role')))
                ->count() <= 1;
        @endphp
        @if(master_can('users.delete') && $user->id !== auth()->id() && ! $isLastSuperAdmin)
            <button type="submit" formaction="{{ route('admin.users.destroy', $user) }}" formmethod="POST"
                class="btn btn-outline btn-sm btn-danger-text"
                onclick="return confirm('Delete this user?')">
                @csrf @method('DELETE') Delete
            </button>
        @endif
    </div>
</form>
@endsection
