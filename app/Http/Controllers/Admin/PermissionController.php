<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Services\MasterPermissionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->paginate(30);

        return view('admin.permissions.index', compact('permissions'));
    }

    public function create(): View
    {
        $groups = Permission::query()
            ->whereNotNull('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return view('admin.permissions.create', compact('groups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        Permission::create($validated);

        return redirect()->route('admin.permissions.index')->with('success', 'Permission created.');
    }

    public function edit(Permission $permission): View
    {
        $groups = Permission::query()
            ->whereNotNull('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return view('admin.permissions.edit', compact('permission', 'groups'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $permission->update($this->validated($request, $permission));

        return redirect()->route('admin.permissions.edit', $permission)->with('success', 'Permission updated.');
    }

    public function syncFromConfig(MasterPermissionSyncService $sync): RedirectResponse
    {
        $result = $sync->sync();

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', "Permission catalog synced ({$result['inserted']} new, {$result['total']} total).");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Permission $permission = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('permissions', 'name')->ignore($permission?->id),
            ],
            'group' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
