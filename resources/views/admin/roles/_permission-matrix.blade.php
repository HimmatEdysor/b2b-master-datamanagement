@php
    $selected = old('permissions', isset($role) ? $role->permissions->pluck('id')->all() : []);
    $orderedGroups = collect($permissionGroups ?? [])
        ->map(fn ($perms, $group) => [
            'group' => $group,
            'permissions' => $perms,
            'count' => $perms instanceof \Illuminate\Support\Collection ? $perms->count() : count($perms),
        ])
        ->sortBy('count')
        ->values();
@endphp

@if($orderedGroups->isEmpty())
    <p class="cell-muted">No active permissions. Run sync from <a href="{{ route('admin.permissions.index') }}">Permissions</a>.</p>
@else
    <div class="permission-matrix-toolbar">
        <label class="permission-select-all">
            <input type="checkbox" id="permission-select-all">
            Select / deselect all
        </label>
    </div>
    <div class="permission-matrix-grid">
        @foreach($orderedGroups as $g)
            @php
                $groupName = $g['group'] ?? 'Other';
                $permissions = $g['permissions'];
            @endphp
            <div class="permission-card">
                <div class="permission-card-header">
                    <label class="permission-group-toggle">
                        <input type="checkbox" class="permission-group-checkbox" data-group="{{ $groupName }}">
                        <span>{{ $groupName }}</span>
                    </label>
                </div>
                <ul class="permission-card-body">
                    @foreach($permissions as $permission)
                        <li>
                            <label>
                                <input type="checkbox"
                                       class="permission-item-checkbox"
                                       name="permissions[]"
                                       value="{{ $permission->id }}"
                                       data-group="{{ $groupName }}"
                                       @checked(in_array($permission->id, $selected))>
                                <code>{{ $permission->name }}</code>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif

@once
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('permission-select-all');
            const groupBoxes = document.querySelectorAll('.permission-group-checkbox');
            const itemBoxes = document.querySelectorAll('.permission-item-checkbox');

            function updateGroupState(groupName) {
                const items = Array.from(itemBoxes).filter(cb => cb.dataset.group === groupName);
                const group = Array.from(groupBoxes).find(cb => cb.dataset.group === groupName);
                if (!group || items.length === 0) return;
                const all = items.every(cb => cb.checked);
                const any = items.some(cb => cb.checked);
                group.checked = all;
                group.indeterminate = !all && any;
            }

            groupBoxes.forEach(group => {
                group.addEventListener('change', function () {
                    const name = this.dataset.group;
                    itemBoxes.forEach(cb => {
                        if (cb.dataset.group === name) cb.checked = this.checked;
                    });
                });
            });

            itemBoxes.forEach(cb => {
                cb.addEventListener('change', () => updateGroupState(cb.dataset.group));
            });

            groupBoxes.forEach(g => updateGroupState(g.dataset.group));

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    groupBoxes.forEach(g => { g.checked = this.checked; g.indeterminate = false; });
                    itemBoxes.forEach(cb => { cb.checked = this.checked; });
                });
            }
        });
    </script>
    @endpush
@endonce
