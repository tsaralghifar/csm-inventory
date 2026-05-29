<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    // ── CRUD User (tidak berubah dari asli) ───────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage-users');

        $query = User::with(['roles', 'warehouse']);
        if ($request->search) {
            $query->where(fn($q) => $q
                ->where('name', 'ilike', "%{$request->search}%")
                ->orWhere('email', 'ilike', "%{$request->search}%")
            );
        }
        if ($request->role)         $query->role($request->role);
        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);

        $users = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $users->items(),
            'meta'    => ['total' => $users->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage-users');

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password'     => 'required|string|min:8',
            'phone'        => 'nullable|string|max:20',
            'employee_id'  => 'nullable|string|unique:users',
            'position'     => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'role'         => 'required|exists:roles,name,guard_name,web',
        ]);

        $user = User::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'password'     => bcrypt($validated['password']),
            'phone'        => $validated['phone']        ?? null,
            'employee_id'  => $validated['employee_id']  ?? null,
            'position'     => $validated['position']     ?? null,
            'warehouse_id' => $validated['warehouse_id'] ?? null,
        ]);

        $user->assignRole(Role::findByName($validated['role'], 'web'));

        return response()->json([
            'success' => true,
            'data'    => $user->load('roles', 'warehouse'),
            'message' => 'User berhasil dibuat',
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('manage-users');

        return response()->json([
            'success' => true,
            'data'    => $user->load('roles', 'warehouse', 'permissions'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage-users');

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'email'        => "sometimes|email|unique:users,email,{$user->id}",
            'phone'        => 'nullable|string|max:20',
            'position'     => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active'    => 'sometimes|boolean',
            'role'         => 'sometimes|exists:roles,name,guard_name,web',
        ]);

        if (isset($validated['role'])) {
            $user->syncRoles([Role::findByName($validated['role'], 'web')]);
            unset($validated['role']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->load('roles', 'warehouse'),
            'message' => 'User berhasil diperbarui',
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage-users');
        $request->validate(['password' => 'required|string|min:8']);
        $user->update(['password' => bcrypt($request->password)]);

        return response()->json(['success' => true, 'message' => 'Password berhasil direset']);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('manage-users');

        if ($user->id === request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menghapus akun sendiri',
            ], 422);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'User berhasil dihapus']);
    }

    // ── Roles & Permissions (tidak berubah dari asli) ─────────────────────

    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->where('guard_name', 'web')->get();
        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'web')
            ->get()
            ->groupBy(fn($p) => explode('-', $p->name)[1] ?? 'other');

        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function updateRolePermissions(Request $request): JsonResponse
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

    // ── Tanda Tangan Digital ──────────────────────────────────────────────

    /**
     * POST /users/signature
     * Upload tanda tangan milik user yang sedang login.
     *
     * Body (multipart/form-data):
     *   signature_file  — file PNG/JPG (max 2MB)
     *
     * Body (application/json):
     *   signature_base64 — string base64 PNG (boleh dengan/tanpa prefix "data:...")
     */
    public function uploadSignature(Request $request): JsonResponse
    {
        $user = $request->user();

        // ── Terima file upload ──────────────────────────────────────────
        if ($request->hasFile('signature_file')) {
            $request->validate([
                'signature_file' => 'required|file|mimes:png,jpg,jpeg|max:2048',
            ]);

            $file     = $request->file('signature_file');
            $base64   = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();
            $dataUri  = "data:{$mimeType};base64,{$base64}";

            $user->update(['signature' => $dataUri]);

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan',
                'data'    => [
                    'has_signature' => true,
                    'preview_url'   => $dataUri,
                ],
            ]);
        }

        // ── Terima base64 langsung (dari canvas/signature pad) ──────────
        if ($request->filled('signature_base64')) {
            $request->validate([
                'signature_base64' => 'required|string',
            ]);

            $raw     = $request->input('signature_base64');
            $dataUri = str_starts_with($raw, 'data:') ? $raw : 'data:image/png;base64,' . $raw;

            // Validasi: pastikan benar-benar base64 yang valid
            $pureBase64 = preg_replace('/^data:[^;]+;base64,/', '', $dataUri);
            if (base64_decode($pureBase64, strict: true) === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format base64 tidak valid',
                ], 422);
            }

            $user->update(['signature' => $dataUri]);

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan',
                'data'    => [
                    'has_signature' => true,
                    'preview_url'   => $dataUri,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Kirimkan signature_file (PNG/JPG) atau signature_base64',
        ], 422);
    }

    /**
     * DELETE /users/signature
     * Hapus tanda tangan milik user yang sedang login.
     */
    public function deleteSignature(Request $request): JsonResponse
    {
        $request->user()->update(['signature' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil dihapus',
        ]);
    }

    /**
     * POST /users/{user}/signature
     * Upload tanda tangan untuk user lain — hanya superuser.
     * Dipakai dari modal Edit User di halaman Manajemen User.
     */
    public function uploadSignatureFor(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage-users');

        $request->validate([
            'signature_file' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        $file     = $request->file('signature_file');
        $base64   = base64_encode(file_get_contents($file->getRealPath()));
        $mimeType = $file->getMimeType();
        $dataUri  = "data:{$mimeType};base64,{$base64}";

        $user->update(['signature' => $dataUri]);

        return response()->json([
            'success' => true,
            'message' => "Tanda tangan {$user->name} berhasil disimpan",
            'data'    => [
                'has_signature' => true,
                'preview_url'   => $dataUri,
            ],
        ]);
    }

    /**
     * DELETE /users/{user}/signature
     * Hapus tanda tangan user lain — hanya superuser.
     */
    public function deleteSignatureFor(User $user): JsonResponse
    {
        $this->authorize('manage-users');

        $user->update(['signature' => null]);

        return response()->json([
            'success' => true,
            'message' => "Tanda tangan {$user->name} berhasil dihapus",
        ]);
    }

    /**
     * GET /users/signable
     * Daftar user aktif yang sudah punya TTD.
     * Dipakai oleh modal "Pilih Penandatangan" sebelum export PDF laporan.
     * — Eager load 'roles' dan 'warehouse' untuk hindari N+1 query.
     */
    public function signableUsers(): JsonResponse
    {
        $users = User::with(['roles', 'warehouse'])
            ->active()
            ->hasSignature()
            ->get()
            ->map(fn($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'position'  => $u->position ?? $u->roles->first()?->name,
                'role'      => $u->roles->first()?->name,
                'warehouse' => $u->warehouse?->name,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * GET /users/signature-status
     * Status TTD semua user aktif — hanya superuser/admin_ho.
     * — Eager load 'roles' dan 'warehouse' untuk hindari N+1 query.
     */
    public function signatureStatus(): JsonResponse
    {
        $this->authorize('manage-users');

        $users = User::with(['roles', 'warehouse'])
            ->active()
            ->get()
            ->map(fn($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'position'      => $u->position,
                'role'          => $u->roles->first()?->name,
                'warehouse'     => $u->warehouse?->name,
                'has_signature' => $u->hasSignature(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $users,
            'summary' => [
                'total'         => $users->count(),
                'has_signature' => $users->where('has_signature', true)->count(),
                'no_signature'  => $users->where('has_signature', false)->count(),
            ],
        ]);
    }

    /**
     * GET /profile
     * Profil user yang sedang login termasuk status & preview TTD.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles', 'warehouse');

        return response()->json([
            'success' => true,
            'data'    => array_merge($user->toArray(), [
                'has_signature'     => $user->hasSignature(),
                'signature_preview' => $user->signatureDataUri(),
            ]),
        ]);
    }
}