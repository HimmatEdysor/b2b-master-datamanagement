<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()->withCount('users')->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissionGroups = $this->groupedPermissions();

        return view('admin.roles.create', compact('permissionGroups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $role = Role::create([
            ...$validated,
            'created_by' => auth()->id(),
            'is_active' => true,
        ]);
        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): View
    {
        if ($role->isProtected()) {
            return view('admin.roles.edit', [
                'role' => $role,
                'permissionGroups' => [],
                'protected' => true,
            ]);
        }

        $permissionGroups = $this->groupedPermissions();
        $role->load('permissions');

        return view('admin.roles.edit', [
            'role' => $role,
            'permissionGroups' => $permissionGroups,
            'protected' => false,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->isProtected()) {
            return back()->with('error', 'This role is protected and cannot be modified.');
        }

        $validated = $this->validated($request, $role);
        $role->update($validated);
        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.edit', $role)->with('success', 'Role updated.');
    }

    public function changeStatus(Request $request, Role $role): JsonResponse
    {
        if ($role->isProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'Protected roles cannot be deactivated.',
            ], 422);
        }

        $role->is_active = ! $role->is_active;
        $role->save();

        return response()->json([
            'success' => true,
            'status' => $role->is_active,
            'message' => 'Role status updated.',
        ]);
    }

    /**
     * Group permissions by module; sort groups by count (asc), like B2B CRM role edit.
     *
     * @return array<string, Collection<int, Permission>>
     */
    private function groupedPermissions(): array
    {
        return Permission::query()
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => $p->group ?? 'Other')
            ->sortBy(fn ($group) => $group->count())
            ->all();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        return $validated;
    }
}
