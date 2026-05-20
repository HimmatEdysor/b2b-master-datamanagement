<div class="form-group">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $role->name ?? '') }}" required class="form-control">
</div>
<div class="form-group">
    <label for="slug">Slug</label>
    <input type="text" id="slug" name="slug" value="{{ old('slug', $role->slug ?? '') }}" class="form-control" placeholder="auto from name"
        @if(isset($role) && $role->isProtected()) readonly @endif>
</div>
<div class="form-group">
    <label for="description">Description</label>
    <input type="text" id="description" name="description" value="{{ old('description', $role->description ?? '') }}" class="form-control">
</div>

@if(!empty($protected))
    <p class="alert alert-error">This is a protected system role. Permissions are managed automatically.</p>
@else
    <h3 class="form-section-title">Permissions</h3>
    @include('admin.roles._permission-matrix', ['permissionGroups' => $permissionGroups ?? [], 'role' => $role ?? null])
@endif
