<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage-users');

        $query = User::with(['roles', 'warehouse']);
        if ($request->search) $query->where(fn($q) => $q->where('name', 'ilike', "%{$request->search}%")->orWhere('email', 'ilike', "%{$request->search}%"));
        if ($request->role) $query->role($request->role);
        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);

        $users = $query->paginate($request->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $users->items(), 'meta' => ['total' => $users->total()]]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-users');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'employee_id' => 'nullable|string|unique:users',
            'position' => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'role' => 'required|exists:roles,name,guard_name,web',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'position' => $validated['position'] ?? null,
            'warehouse_id' => $validated['warehouse_id'] ?? null,
        ]);

        $user->assignRole(Role::findByName($validated['role'], 'web'));
        return response()->json(['success' => true, 'data' => $user->load('roles', 'warehouse'), 'message' => 'User berhasil dibuat'], 201);
    }

    public function show(User $user)
    {
        $this->authorize('manage-users');
        return response()->json(['success' => true, 'data' => $user->load('roles', 'warehouse', 'permissions')]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('manage-users');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$user->id}",
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'sometimes|boolean',
            'role' => 'sometimes|exists:roles,name,guard_name,web',
        ]);

        if (isset($validated['role'])) {
            $user->syncRoles([Role::findByName($validated['role'], 'web')]);
            unset($validated['role']);
        }

        $user->update($validated);
        return response()->json(['success' => true, 'data' => $user->load('roles', 'warehouse'), 'message' => 'User berhasil diperbarui']);
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('manage-users');
        $request->validate(['password' => 'required|string|min:8']);
        $user->update(['password' => bcrypt($request->password)]);
        return response()->json(['success' => true, 'message' => 'Password berhasil direset']);
    }

    public function destroy(User $user)
    {
        $this->authorize('manage-users');
        if ($user->id === request()->user()->id) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa menghapus akun sendiri'], 422);
        }
        $user->delete();
        return response()->json(['success' => true, 'message' => 'User berhasil dihapus']);
    }

    public function roles()
    {
        $roles = Role::with('permissions')->where('guard_name', 'web')->get();
        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function permissions()
    {
        $permissions = Permission::where('guard_name', 'web')
            ->get()
            ->groupBy(fn($p) => explode('-', $p->name)[1] ?? 'other');
        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function updateRolePermissions(Request $request)
    {
        $this->authorize('manage-roles');

        $request->validate([
            'role'          => 'required|exists:roles,name,guard_name,web',
            'permissions'   => 'present|array',
            'permissions.*' => 'exists:permissions,name,guard_name,web',
        ]);

        $role = Role::findByName($request->role, 'web');
        $role->syncPermissions($request->permissions);

        return response()->json(['success' => true, 'message' => 'Permission berhasil diperbarui']);
    }
}