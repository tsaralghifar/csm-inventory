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

        // Tambah has_signature dan signature_preview ke setiap user
        // agar tabel & modal Edit User bisa tampilkan status TTD tanpa request tambahan
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
                'signature_file' => 'required|file|mimes:png,jpg,jpeg|max:3072',
            ]);

            $dataUri = $this->processSignatureImage($request->file('signature_file'));

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
            'signature_file' => 'required|file|mimes:png,jpg,jpeg|max:3072',
        ]);

        $file     = $request->file('signature_file');
        $dataUri  = $this->processSignatureImage($file);

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
     * GET /profile-signature/{user}
     * Ambil data tanda tangan user tertentu.
     * Dipakai oleh frontend saat build HTML print (PM, PO) untuk embed TTD.
     * Semua role bisa akses (bukan data sensitif, hanya gambar TTD).
     */
    public function getSignature(User $user): JsonResponse
    {
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
    // PRIVATE HELPER
    // ══════════════════════════════════════════════════════════════════

    /**
     * Proses gambar TTD yang diupload:
     * 1. Auto-crop: pangkas area transparan / putih di semua sisi (trim)
     * 2. Resize: perbesar/perkecil agar pas di kotak PDF (maks 900×360 px)
     * 3. Simpan sebagai PNG transparan (base64 data URI)
     *
     * Jika ekstensi GD tidak tersedia, fallback ke encode langsung (tidak diproses).
     */
    private function processSignatureImage(UploadedFile $file): string
    {
        // Fallback jika GD tidak ada
        if (!extension_loaded('gd')) {
            $base64   = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();
            return "data:{$mimeType};base64,{$base64}";
        }

        $mime = $file->getMimeType();
        $path = $file->getRealPath();

        // Cek dimensi & estimasi memory sebelum load
        $info  = @getimagesize($path);
        $origW = $info[0] ?? 0;
        $origH = $info[1] ?? 0;

        // Estimasi memory yg dibutuhkan GD: W * H * 4 bytes (RGBA) * 2 (src+dst)
        $estimatedBytes = $origW * $origH * 4 * 2;
        $availableBytes = $this->getAvailableMemory();

        // Jika memory tidak cukup, fallback langsung ke raw encode (skip GD)
        if ($estimatedBytes > $availableBytes * 0.8) {
            $base64 = base64_encode(file_get_contents($path));
            return "data:{$mime};base64,{$base64}";
        }

        // Load gambar sesuai tipe
        $src = match (true) {
            str_contains($mime, 'jpeg') => imagecreatefromjpeg($path),
            str_contains($mime, 'jpg')  => imagecreatefromjpeg($path),
            str_contains($mime, 'png')  => imagecreatefrompng($path),
            default                      => imagecreatefromjpeg($path),
        };

        if (!$src) {
            // Gagal baca gambar, fallback ke raw
            $base64 = base64_encode(file_get_contents($path));
            return "data:{$mime};base64,{$base64}";
        }

        // ── 1. Auto-crop: buang baris/kolom putih/transparan di tepi ────
        $src = $this->autoCropSignature($src);

        // ── 2. Resize proporsional ke dalam batas 900 × 360 px ──────────
        $TARGET_W = 900;
        $TARGET_H = 360;

        $w = imagesx($src);
        $h = imagesy($src);

        $ratio  = min($TARGET_W / $w, $TARGET_H / $h); // scale up/down agar memenuhi kotak
        $newW   = (int) round($w * $ratio);
        $newH   = (int) round($h * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        imagedestroy($src);

        // ── 3. Encode ke PNG base64 ──────────────────────────────────────
        ob_start();
        imagepng($dst, null, 6); // kompresi sedang
        $pngData = ob_get_clean();
        imagedestroy($dst);

        $base64 = base64_encode($pngData);
        return "data:image/png;base64,{$base64}";
    }

    /**
     * Auto-crop: potong baris & kolom yang "kosong" (putih atau transparan)
     * dari keempat sisi gambar.
     * Threshold: pixel dianggap kosong jika brightness > 240 atau alpha > 100.
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
            $alpha = ($rgba >> 24) & 0x7F; // 0=opaque, 127=transparent
            if ($alpha > 80) return true;   // mostly transparent
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8)  & 0xFF;
            $b =  $rgba        & 0xFF;
            return ($r > 240 && $g > 240 && $b > 240); // mostly white
        };

        // Top
        for ($y = 0; $y < $h; $y++) {
            $blank = true;
            for ($x = 0; $x < $w; $x++) {
                if (!$isEmpty($x, $y)) { $blank = false; break; }
            }
            if (!$blank) { $top = $y; break; }
        }

        // Bottom
        for ($y = $h - 1; $y >= $top; $y--) {
            $blank = true;
            for ($x = 0; $x < $w; $x++) {
                if (!$isEmpty($x, $y)) { $blank = false; break; }
            }
            if (!$blank) { $bottom = $y; break; }
        }

        // Left
        for ($x = 0; $x < $w; $x++) {
            $blank = true;
            for ($y = $top; $y <= $bottom; $y++) {
                if (!$isEmpty($x, $y)) { $blank = false; break; }
            }
            if (!$blank) { $left = $x; break; }
        }

        // Right
        for ($x = $w - 1; $x >= $left; $x--) {
            $blank = true;
            for ($y = $top; $y <= $bottom; $y++) {
                if (!$isEmpty($x, $y)) { $blank = false; break; }
            }
            if (!$blank) { $right = $x; break; }
        }

        // Tambah padding 4px di setiap sisi
        $pad    = 4;
        $top    = max(0, $top - $pad);
        $bottom = min($h - 1, $bottom + $pad);
        $left   = max(0, $left - $pad);
        $right  = min($w - 1, $right + $pad);

        $cropW = $right  - $left + 1;
        $cropH = $bottom - $top  + 1;

        // Jika tidak ada yang perlu di-crop (gambar sudah bersih)
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

    /**
     * Estimasi memory PHP yang tersisa (dalam bytes).
     */
    private function getAvailableMemory(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return PHP_INT_MAX;

        $unit  = strtolower(substr($limit, -1));
        $value = (int) $limit;
        $bytes = match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };

        return max(0, $bytes - memory_get_usage(true));
    }
}