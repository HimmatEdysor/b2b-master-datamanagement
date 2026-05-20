<div class="form-group">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $user->name ?? '') }}" required class="form-control">
    @error('name')<p class="form-error">{{ $message }}</p>@enderror
</div>
<div class="form-group">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="{{ old('email', $user->email ?? '') }}" required class="form-control">
    @error('email')<p class="form-error">{{ $message }}</p>@enderror
</div>
<div class="form-group">
    <label for="password">Password @isset($user)<span class="text-muted">(leave blank to keep)</span>@endisset</label>
    <input type="password" id="password" name="password" class="form-control" @empty($user) required @endempty>
    @error('password')<p class="form-error">{{ $message }}</p>@enderror
</div>
<div class="form-group">
    <label for="password_confirmation">Confirm password</label>
    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" @empty($user) required @endempty>
</div>

<fieldset class="roles-fieldset">
    <legend>Roles</legend>
    <p class="form-hint">Assign one or more roles (e.g. Super Admin, Support Agent).</p>
    <div class="roles-checkbox-grid">
        @foreach($roles as $role)
            <label class="role-checkbox-card">
                <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                    @checked(in_array($role->id, old('roles', isset($user) ? $user->roles->pluck('id')->all() : [])))>
                <span class="role-checkbox-title">{{ $role->name }}</span>
                <code class="code-pill">{{ $role->slug }}</code>
                @if($role->isProtected())
                    <span class="cell-muted">Protected</span>
                @endif
            </label>
        @endforeach
    </div>
</fieldset>
