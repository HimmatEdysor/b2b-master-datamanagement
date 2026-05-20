<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\MasterAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('roles')->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->roles()->sync($validated['roles'] ?? []);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $roles = Role::query()->orderBy('name')->get();
        $user->load('roles');

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        if ($this->mustKeepSuperAdminRole($user, $validated['roles'] ?? [])) {
            return back()
                ->withInput()
                ->with('error', 'At least one Super Admin user must keep the Super Admin role.');
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->roles()->sync($validated['roles'] ?? []);

        if ($user->id === auth()->id()) {
            MasterAuth::syncSessionPermissions($user);
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isSuperAdmin() && $this->superAdminCount() <= 1) {
            return back()->with('error', 'Cannot delete the last Super Admin user.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    /** @param  list<int>  $roleIds */
    private function mustKeepSuperAdminRole(User $user, array $roleIds): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        $superAdminRoleId = Role::query()
            ->where('slug', config('master_permissions.super_admin_role', 'super-admin'))
            ->value('id');

        if (! $superAdminRoleId) {
            return false;
        }

        if (in_array((int) $superAdminRoleId, array_map('intval', $roleIds), true)) {
            return false;
        }

        return $this->superAdminCount() <= 1;
    }

    private function superAdminCount(): int
    {
        $slug = config('master_permissions.super_admin_role', 'super-admin');

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', $slug))
            ->count();
    }
}
