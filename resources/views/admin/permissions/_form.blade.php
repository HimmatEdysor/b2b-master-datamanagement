<div class="form-group">
    <label for="name">Permission key</label>
    <input type="text" id="name" name="name" value="{{ old('name', $permission->name ?? '') }}" required class="form-control" placeholder="e.g. tenants.view">
    @error('name')<p class="form-error">{{ $message }}</p>@enderror
</div>
<div class="form-group">
    <label for="group">Group</label>
    <input type="text" id="group" name="group" value="{{ old('group', $permission->group ?? '') }}" class="form-control" list="permission-groups">
    <datalist id="permission-groups">
        @foreach($groups ?? [] as $group)
            <option value="{{ $group }}">
        @endforeach
    </datalist>
</div>
<div class="form-group">
    <label for="description">Description</label>
    <input type="text" id="description" name="description" value="{{ old('description', $permission->description ?? '') }}" class="form-control">
</div>
<div class="form-group">
    <label class="checkbox-inline">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $permission->is_active ?? true))>
        Active
    </label>
</div>
