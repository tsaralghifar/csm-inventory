<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DocumentSignerController
 *
 * GET  /document-signers/{type}/{id}              — load status semua slot
 * POST /document-signers/{type}/{id}/slot         — tambah/update 1 slot TTD
 * POST /document-signers/{type}/{id}/finalize     — kunci dokumen permanen
 */
class DocumentSignerController extends Controller
{
    private const ALLOWED_TYPES = [
        'permintaan_material',
        'purchase_order',
        'bon_pengeluaran',
        'surat_jalan',
        'transfer_barang',
    ];

    private const SLOT_LABELS = [
        1 => 'Dibuat oleh',
        2 => 'Diperiksa oleh',
        3 => 'Disetujui oleh',
    ];

    // ── GET /document-signers/{type}/{id} ─────────────────────────────────────

    /**
     * Load status dokumen: slot yang terisi, is_finalized, dll.
     *
     * Response:
     * {
     *   success: true,
     *   is_finalized: bool,
     *   finalized_at: string|null,
     *   slots: [
     *     { order:1, label, name, position, signature, signed_at } | null,
     *     null,   // slot 2 kosong
     *     null,   // slot 3 kosong
     *   ]
     * }
     */
    public function show(string $type, string $id): JsonResponse
    {
        if (! in_array($type, self::ALLOWED_TYPES)) {
            return response()->json(['success' => false, 'message' => 'Tipe dokumen tidak valid.'], 422);
        }

        $status = DocumentSigner::loadStatus($type, $id);

        return response()->json([
            'success'      => true,
            'is_finalized' => $status['is_finalized'],
            'finalized_at' => $status['finalized_at'],
            'slots'        => $status['slots'],
        ]);
    }

    // ── POST /document-signers/{type}/{id}/slot ───────────────────────────────

    /**
     * Tambah atau update satu slot TTD.
     *
     * Body: { slot: 1|2|3 }
     * User yang login otomatis menjadi penandatangan di slot tersebut.
     * Gagal jika dokumen sudah difinalisasi.
     */
    public function addSlot(Request $request, string $type, string $id): JsonResponse
    {
        if (! in_array($type, self::ALLOWED_TYPES)) {
            return response()->json(['success' => false, 'message' => 'Tipe dokumen tidak valid.'], 422);
        }

        if (DocumentSigner::isFinalized($type, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen sudah difinalisasi dan tidak dapat diubah.',
            ], 409);
        }

        $slot = (int) $request->input('slot', 1);
        if (! in_array($slot, [1, 2, 3])) {
            return response()->json(['success' => false, 'message' => 'Slot tidak valid (1-3).'], 422);
        }

        /** @var User $user */
        $user = $request->user();

        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'Akun tidak aktif.'], 403);
        }

        if (! $user->hasSignature()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum mengupload tanda tangan. Upload TTD di halaman Profil terlebih dahulu.',
            ], 422);
        }

        $signer = [
            'label'      => self::SLOT_LABELS[$slot],
            'name'       => $user->name,
            'position'   => $user->position ?? $user->roles->first()?->name ?? '—',
            'role'       => $user->roles->first()?->name,
            'user_id'    => $user->id,
            'user_model' => $user,
        ];

        DocumentSigner::addSlot($type, $id, $signer, $slot);

        $status = DocumentSigner::loadStatus($type, $id);

        return response()->json([
            'success' => true,
            'message' => "TTD Anda berhasil ditambahkan ke slot {$slot}.",
            'is_finalized' => $status['is_finalized'],
            'slots'        => $status['slots'],
        ], 201);
    }

    // ── POST /document-signers/{type}/{id}/finalize ───────────────────────────

    /**
     * Finalisasi dokumen — kunci permanen.
     * Hanya superuser atau admin_ho yang boleh finalisasi.
     * Minimal 1 slot harus sudah terisi.
     */
    public function finalize(Request $request, string $type, string $id): JsonResponse
    {
        if (! in_array($type, self::ALLOWED_TYPES)) {
            return response()->json(['success' => false, 'message' => 'Tipe dokumen tidak valid.'], 422);
        }

        /** @var User $user */
        $user = $request->user();

        // Hanya superuser dan admin_ho yang boleh finalisasi
        $canFinalize = $user->isSuperuser()
            || $user->hasRole('admin_ho')
            || $user->hasRole('manager')
            || $user->hasRole('logistik_ho');

        if (! $canFinalize) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berwenang memfinalisasi dokumen ini.',
            ], 403);
        }

        if (DocumentSigner::isFinalized($type, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen sudah difinalisasi sebelumnya.',
            ], 409);
        }

        if (! DocumentSigner::hasAnySlot($type, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tanda tangan yang bisa difinalisasi. Tambahkan minimal 1 TTD terlebih dahulu.',
            ], 422);
        }

        $ok = DocumentSigner::finalize($type, $id, $user->id);

        if (! $ok) {
            return response()->json(['success' => false, 'message' => 'Gagal memfinalisasi dokumen.'], 500);
        }

        $status = DocumentSigner::loadStatus($type, $id);

        return response()->json([
            'success'      => true,
            'message'      => 'Dokumen berhasil difinalisasi. Tanda tangan tidak dapat diubah lagi.',
            'is_finalized' => true,
            'finalized_at' => $status['finalized_at'],
            'slots'        => $status['slots'],
        ]);
    }
}