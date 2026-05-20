@extends('layouts.admin')
@section('title', 'My profile')
@section('page-title', 'My profile')

@section('content')
<div class="profile-layout">
    <div class="card profile-summary-card">
        <div class="profile-summary-header">
            <span class="user-avatar user-avatar-lg" aria-hidden="true">{{ $user->initials() }}</span>
            <div>
                <h2 class="profile-summary-name">{{ $user->name }}</h2>
                <p class="profile-summary-email">{{ $user->email }}</p>
            </div>
        </div>
        <dl class="profile-meta">
            <div>
                <dt>Roles</dt>
                <dd>
                    @forelse($user->roles as $role)
                        <span class="badge badge-active">{{ $role->name }}</span>
                    @empty
                        <span class="cell-empty">No roles assigned</span>
                    @endforelse
                </dd>
            </div>
            <div>
                <dt>Member since</dt>
                <dd>{{ $user->created_at?->format('d M Y') ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <form method="POST" action="{{ route('admin.profile.update') }}" class="card admin-form-card profile-edit-card">
        @csrf
        @method('PUT')
        <h2 class="card-title">Edit profile</h2>
        <div class="form-group">
            <label for="profile-name">Name</label>
            <input type="text" id="profile-name" name="name" value="{{ old('name', $user->name) }}" required class="form-control">
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="profile-email">Email</label>
            <input type="email" id="profile-email" name="email" value="{{ old('email', $user->email) }}" required class="form-control">
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="profile-password">New password <span class="text-muted">(leave blank to keep current)</span></label>
            <input type="password" id="profile-password" name="password" class="form-control" autocomplete="new-password">
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="profile-password-confirmation">Confirm new password</label>
            <input type="password" id="profile-password-confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection
