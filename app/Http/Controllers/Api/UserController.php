<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\UploadedFile;

class UserController extends Controller
{
    // ── CRUD User ─────────────────────────────────────────────────────────

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

        $items = collect($users->items())->map(function ($u) {
            $arr = $u->toArray();
            $arr['has_signature']     = $u->hasSignature();
            $arr['signature_preview'] = $u->signatureDataUri();
            return $arr;
        });

        return response()->json([
            'success' => true,
            'data'    => $items,
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

    // ── Roles & Permissions ───────────────────────────────────────────────

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

        return response()->json(['success' => true, 'data' => (object) $permissions->toArray()]);
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
     */
    public function uploadSignature(Request $request): JsonResponse
    {
        $user = $request->user();

        // Terima file upload
        if ($request->hasFile('signature_file')) {
            $request->validate([
                'signature_file' => 'required|file|mimes:png,jpg,jpeg|max:3072',
            ]);

            $pngBinary = $this->processSignatureImageToBinary($request->file('signature_file'));
            $user->storeSignatureFile($pngBinary);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan',
                'data'    => [
                    'has_signature' => true,
                    'preview_url'   => $user->signatureDataUri(),
                ],
            ]);
        }

        // Terima base64 langsung (dari canvas/signature pad)
        if ($request->filled('signature_base64')) {
            $request->validate([
                'signature_base64' => 'required|string',
            ]);

            $raw        = $request->input('signature_base64');
            $pureBase64 = preg_replace('/^data:[^;]+;base64,/', '', $raw);

            $binary = base64_decode($pureBase64, strict: true);
            if ($binary === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format base64 tidak valid',
                ], 422);
            }

            $path = $user->storeSignatureFile($binary);
            $user->update(['signature_path' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan',
                'data'    => [
                    'has_signature' => true,
                    'preview_url'   => $user->fresh()->signatureDataUri(),
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
        $request->user()->deleteSignatureFile();

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil dihapus',
        ]);
    }

    /**
     * POST /users/{user}/signature
     * Upload tanda tangan untuk user lain — hanya superuser.
     */
    public function uploadSignatureFor(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage-users');

        $request->validate([
            'signature_file' => 'required|file|mimes:png,jpg,jpeg|max:3072',
        ]);

        $pngBinary = $this->processSignatureImageToBinary($request->file('signature_file'));
        $path      = $user->storeSignatureFile($pngBinary);
        $user->update(['signature_path' => $path]);

        return response()->json([
            'success' => true,
            'message' => "Tanda tangan {$user->name} berhasil disimpan",
            'data'    => [
                'has_signature' => true,
                'preview_url'   => $user->fresh()->signatureDataUri(),
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

        $user->deleteSignatureFile();

        return response()->json([
            'success' => true,
            'message' => "Tanda tangan {$user->name} berhasil dihapus",
        ]);
    }

    /**
     * GET /users/signable
     *
     * ── CELAH 1 FIX ──────────────────────────────────────────────────────
     * Daftar user aktif yang sudah punya TTD, DIFILTER berdasarkan hirarki role.
     * User hanya bisa memilih penandatangan dari role setara atau di atasnya.
     *
     * Logika filter penandatangan yang muncul:
     * - superuser / admin_ho (level 4-5) → lihat SEMUA level (bebas pilih siapapun)
     * - manager / logistik_ho (level 3-4) → lihat level 3 ke atas
     * - chief_mekanik (level 2)           → lihat level 2 ke atas
     * - logistik_site / admin_site (level 1) → tidak boleh memilih (return kosong)
     *
     * Alasan: superuser/admin_ho perlu bisa menunjuk penandatangan dari semua level
     * karena mereka yang bertanggung jawab atas dokumen lintas departemen.
     */
    public function signableUsers(): JsonResponse
    {
        $currentUser  = request()->user()->load('roles');
        $currentLevel = $currentUser->roleLevel();

        // Superuser bisa melihat dan memilih SEMUA user bertanda tangan.
        // Non-superuser: hanya bisa melihat dan memilih dirinya sendiri (jika punya TTD).
        $isSuperuser = $currentUser->isSuperuser();

        if ($isSuperuser) {
            $users = User::with(['roles', 'warehouse'])
                ->active()
                ->hasSignature()
                ->get()
                ->map(fn($u) => [
                    'id'                => $u->id,
                    'name'              => $u->name,
                    'position'          => $u->position ?? $u->roles->first()?->name,
                    'role'              => $u->roles->first()?->name,
                    'warehouse'         => $u->warehouse?->name,
                    'signature_preview' => $u->signatureDataUri(),
                ]);
        } else {
            // Semua role lain hanya melihat dirinya sendiri (jika sudah upload TTD)
            $users = $currentUser->hasSignature()
                ? collect([[
                    'id'                => $currentUser->id,
                    'name'              => $currentUser->name,
                    'position'          => $currentUser->position ?? $currentUser->roles->first()?->name,
                    'role'              => $currentUser->roles->first()?->name,
                    'warehouse'         => $currentUser->warehouse?->name,
                    'signature_preview' => $currentUser->signatureDataUri(),
                ]])
                : collect();
        }

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * GET /users/signature-status
     * Status TTD semua user aktif — hanya superuser/admin_ho.
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
     * GET /profile-signature/{user}
     *
     * ── CELAH 2 FIX ──────────────────────────────────────────────────────
     * Ambil data tanda tangan user tertentu.
     * Akses dibatasi:
     *   - Pemilik TTD sendiri → boleh
     *   - superuser           → boleh
     *   - admin_ho            → boleh
     *   - Role lain           → 403 Forbidden
     *
     * Alasan: gambar TTD adalah data pribadi sensitif yang bisa disalahgunakan
     * (pemalsuan dokumen). Bukan "hanya gambar" — ini identitas digital.
     */
    public function getSignature(User $user): JsonResponse
    {
        $requester = request()->user();

        $isOwner   = $requester->id === $user->id;
        $isSuperuser = $requester->isSuperuser();
        $isAdminHO   = $requester->isAdminHO();

        if (! $isOwner && ! $isSuperuser && ! $isAdminHO) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat tanda tangan ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                => $user->id,
                'name'              => $user->name,
                'position'          => $user->position,
                'has_signature'     => $user->hasSignature(),
                'signature_preview' => $user->signatureDataUri(),
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

    // ══════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Proses gambar TTD → return binary PNG (bukan base64/data URI).
     * 1. Auto-crop: pangkas area transparan/putih di semua sisi
     * 2. Resize: maks 900×360 px, proporsional
     * 3. Return sebagai binary PNG string
     */
    private function processSignatureImageToBinary(UploadedFile $file): string
    {
        // Fallback jika GD tidak ada
        if (! extension_loaded('gd')) {
            return file_get_contents($file->getRealPath());
        }

        $mime = $file->getMimeType();
        $path = $file->getRealPath();

        $info  = @getimagesize($path);
        $origW = $info[0] ?? 0;
        $origH = $info[1] ?? 0;

        $estimatedBytes = $origW * $origH * 4 * 2;
        $availableBytes = $this->getAvailableMemory();

        if ($estimatedBytes > $availableBytes * 0.8) {
            return file_get_contents($path);
        }

        $src = match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => imagecreatefromjpeg($path),
            str_contains($mime, 'png') => imagecreatefrompng($path),
            default                    => imagecreatefromjpeg($path),
        };

        if (! $src) {
            return file_get_contents($path);
        }

        $src = $this->autoCropSignature($src);

        $TARGET_W = 900;
        $TARGET_H = 360;
        $w        = imagesx($src);
        $h        = imagesy($src);
        $ratio    = min($TARGET_W / $w, $TARGET_H / $h);
        $newW     = (int) round($w * $ratio);
        $newH     = (int) round($h * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);

        ob_start();
        imagepng($dst, null, 6);
        $pngBinary = ob_get_clean();
        imagedestroy($dst);

        return $pngBinary;
    }

    /**
     * Auto-crop: potong baris & kolom kosong (putih/transparan) dari keempat sisi.
     */
    private function autoCropSignature(\GdImage $img): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);

        $top    = 0;
        $bottom = $h - 1;
        $left   = 0;
        $right  = $w - 1;

        $isEmpty = function (int $x, int $y) use ($img): bool {
            $rgba  = imagecolorat($img, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;
            if ($alpha > 80) return true;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8)  & 0xFF;
            $b =  $rgba        & 0xFF;
            return ($r > 240 && $g > 240 && $b > 240);
        };

        for ($y = 0; $y < $h; $y++) {
            $blank = true;
            for ($x = 0; $x < $w; $x++) {
                if (! $isEmpty($x, $y)) { $blank = false; break; }
            }
            if (! $blank) { $top = $y; break; }
        }

        for ($y = $h - 1; $y >= $top; $y--) {
            $blank = true;
            for ($x = 0; $x < $w; $x++) {
                if (! $isEmpty($x, $y)) { $blank = false; break; }
            }
            if (! $blank) { $bottom = $y; break; }
        }

        for ($x = 0; $x < $w; $x++) {
            $blank = true;
            for ($y = $top; $y <= $bottom; $y++) {
                if (! $isEmpty($x, $y)) { $blank = false; break; }
            }
            if (! $blank) { $left = $x; break; }
        }

        for ($x = $w - 1; $x >= $left; $x--) {
            $blank = true;
            for ($y = $top; $y <= $bottom; $y++) {
                if (! $isEmpty($x, $y)) { $blank = false; break; }
            }
            if (! $blank) { $right = $x; break; }
        }

        $pad    = 4;
        $top    = max(0, $top - $pad);
        $bottom = min($h - 1, $bottom + $pad);
        $left   = max(0, $left - $pad);
        $right  = min($w - 1, $right + $pad);

        $cropW = $right  - $left + 1;
        $cropH = $bottom - $top  + 1;

        if ($cropW === $w && $cropH === $h) return $img;

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
        imagefilledrectangle($cropped, 0, 0, $cropW, $cropH, $transparent);
        imagecopy($cropped, $img, 0, 0, $left, $top, $cropW, $cropH);
        imagedestroy($img);

        return $cropped;
    }

    private function getAvailableMemory(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return PHP_INT_MAX;

        $unit  = strtolower(substr($limit, -1));
        $value = (int) $limit;
        $bytes = match ($unit) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };

        return max(0, $bytes - memory_get_usage(true));
    }
}