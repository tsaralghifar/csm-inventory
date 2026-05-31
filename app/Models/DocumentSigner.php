<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Model DocumentSigner — snapshot penandatangan dokumen (bertahap + finalisasi).
 *
 * Alur:
 *  1. User pertama pilih slot → saveSlot() → is_finalized = false
 *  2. User lain bisa addSlot() ke slot berbeda selama belum finalized
 *  3. User berwenang klik Finalisasi → finalize() → is_finalized = true (permanen)
 *
 * @property string       $document_type
 * @property string       $document_id
 * @property int          $signer_order
 * @property string       $signer_label
 * @property int|null     $signer_user_id
 * @property string       $signer_name
 * @property string|null  $signer_position
 * @property string|null  $signer_role
 * @property string|null  $signature_snapshot_path
 * @property bool         $is_finalized
 * @property int|null     $finalized_by
 * @property \Carbon\Carbon|null $finalized_at
 * @property \Carbon\Carbon $signed_at
 */
class DocumentSigner extends Model
{
    protected $fillable = [
        'document_type',
        'document_id',
        'signer_order',
        'signer_label',
        'signer_user_id',
        'signer_name',
        'signer_position',
        'signer_role',
        'signature_snapshot_path',
        'signed_at',
        'is_finalized',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'signed_at'    => 'datetime',
        'finalized_at' => 'datetime',
        'is_finalized' => 'boolean',
    ];

    // ── Relasi ──────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    // ── Query helpers ────────────────────────────────────────────────────────

    private static function forDoc(string $type, string $id)
    {
        return static::where('document_type', $type)->where('document_id', $id);
    }

    // ── Status ───────────────────────────────────────────────────────────────

    /** Apakah dokumen sudah difinalisasi (terkunci permanen). */
    public static function isFinalized(string $type, string $id): bool
    {
        return static::forDoc($type, $id)->where('is_finalized', true)->exists();
    }

    /** Apakah ada minimal 1 slot yang sudah diisi. */
    public static function hasAnySlot(string $type, string $id): bool
    {
        return static::forDoc($type, $id)->exists();
    }

    /** Slot mana saja yang sudah terisi (1-indexed). */
    public static function usedSlots(string $type, string $id): array
    {
        return static::forDoc($type, $id)->pluck('signer_order')->toArray();
    }

    // ── Write operations ─────────────────────────────────────────────────────

    /**
     * Tambah satu slot TTD ke dokumen.
     * Gagal jika dokumen sudah difinalisasi atau slot sudah terpakai.
     *
     * @param  array  $signer  ['label','name','position','role','user_id','user_model']
     * @param  int    $order   Slot yang dipilih (1, 2, atau 3)
     * @return bool
     */
    public static function addSlot(string $type, string $id, array $signer, int $order): bool
    {
        if (static::isFinalized($type, $id)) return false;

        // Hapus slot lama jika ada (user update slot sendiri)
        $old = static::forDoc($type, $id)->where('signer_order', $order)->first();
        if ($old) {
            if ($old->signature_snapshot_path) {
                Storage::disk('local')->delete($old->signature_snapshot_path);
            }
            $old->delete();
        }

        $snapshotPath = null;
        $userModel    = $signer['user_model'] ?? null;
        if ($userModel && $userModel->hasSignature()) {
            $dir          = "signatures/snapshots/{$type}";
            $snapshotPath = "{$dir}/{$id}_{$order}.png";
            Storage::disk('local')->makeDirectory($dir);
            $binary = Storage::disk('local')->get($userModel->signature_path);
            Storage::disk('local')->put($snapshotPath, $binary);
        }

        static::create([
            'document_type'           => $type,
            'document_id'             => $id,
            'signer_order'            => $order,
            'signer_label'            => $signer['label'],
            'signer_user_id'          => $signer['user_id'] ?? null,
            'signer_name'             => $signer['name'],
            'signer_position'         => $signer['position'] ?? null,
            'signer_role'             => $signer['role'] ?? null,
            'signature_snapshot_path' => $snapshotPath,
            'signed_at'               => now(),
            'is_finalized'            => false,
        ]);

        return true;
    }

    /**
     * Finalisasi dokumen — kunci semua slot, tidak bisa diubah lagi.
     * Minimal 1 slot harus sudah terisi.
     */
    public static function finalize(string $type, string $id, int $finalizedBy): bool
    {
        if (! static::hasAnySlot($type, $id)) return false;
        if (static::isFinalized($type, $id)) return false;

        static::forDoc($type, $id)->update([
            'is_finalized' => true,
            'finalized_by' => $finalizedBy,
            'finalized_at' => now(),
        ]);

        return true;
    }

    /**
     * Simpan snapshot lama (backward compat untuk ReportController).
     * Langsung finalize setelah simpan.
     */
    public static function saveSnapshot(string $type, string $id, array $signers): void
    {
        // Hapus slot lama
        $existing = static::forDoc($type, $id)->get();
        foreach ($existing as $old) {
            if ($old->signature_snapshot_path) {
                Storage::disk('local')->delete($old->signature_snapshot_path);
            }
        }
        static::forDoc($type, $id)->delete();

        $labels = ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh'];

        foreach ($signers as $i => $signer) {
            $order        = $i + 1;
            $snapshotPath = null;
            $userModel    = $signer['user_model'] ?? null;

            if ($userModel && $userModel->hasSignature()) {
                $dir          = "signatures/snapshots/{$type}";
                $snapshotPath = "{$dir}/{$id}_{$order}.png";
                Storage::disk('local')->makeDirectory($dir);
                $binary = Storage::disk('local')->get($userModel->signature_path);
                Storage::disk('local')->put($snapshotPath, $binary);
            }

            static::create([
                'document_type'           => $type,
                'document_id'             => $id,
                'signer_order'            => $order,
                'signer_label'            => $signer['label'] ?? ($labels[$i] ?? 'Penandatangan'),
                'signer_user_id'          => $signer['user_id'] ?? null,
                'signer_name'             => $signer['name'],
                'signer_position'         => $signer['position'] ?? null,
                'signer_role'             => $signer['role'] ?? null,
                'signature_snapshot_path' => $snapshotPath,
                'signed_at'               => now(),
                'is_finalized'            => true, // laporan langsung final
            ]);
        }
    }

    /**
     * Load semua slot (terisi maupun kosong) untuk frontend.
     * Return array 3 elemen — slot kosong = null.
     *
     * @return array{slots: array, is_finalized: bool, finalized_at: string|null}
     */
    public static function loadStatus(string $type, string $id): array
    {
        $records = static::forDoc($type, $id)->orderBy('signer_order')->get();

        $isFinalized = $records->where('is_finalized', true)->isNotEmpty();
        $finalizedAt = $records->where('is_finalized', true)->first()?->finalized_at?->toIso8601String();

        $slots = array_fill(0, 3, null); // slot 1-3, index 0-2

        foreach ($records as $r) {
            $signatureDataUri = null;
            if ($r->signature_snapshot_path
                && Storage::disk('local')->exists($r->signature_snapshot_path)) {
                $binary           = Storage::disk('local')->get($r->signature_snapshot_path);
                $signatureDataUri = 'data:image/png;base64,' . base64_encode($binary);
            }

            $slots[$r->signer_order - 1] = [
                'order'     => $r->signer_order,
                'label'     => $r->signer_label,
                'name'      => $r->signer_name,
                'position'  => $r->signer_position ?? '—',
                'role'      => $r->signer_role,
                'user_id'   => $r->signer_user_id,
                'signature' => $signatureDataUri,
                'signed_at' => $r->signed_at->toIso8601String(),
            ];
        }

        return [
            'slots'        => $slots,
            'is_finalized' => $isFinalized,
            'finalized_at' => $finalizedAt,
        ];
    }

    /**
     * Load snapshot untuk blade PDF (backward compat).
     * Hanya return slot yang terisi.
     */
    public static function loadSnapshot(string $type, string $id): ?array
    {
        $records = static::forDoc($type, $id)->orderBy('signer_order')->get();
        if ($records->isEmpty()) return null;

        return $records->map(function ($r) {
            $signatureDataUri = null;
            if ($r->signature_snapshot_path
                && Storage::disk('local')->exists($r->signature_snapshot_path)) {
                $binary           = Storage::disk('local')->get($r->signature_snapshot_path);
                $signatureDataUri = 'data:image/png;base64,' . base64_encode($binary);
            }
            return [
                'label'     => $r->signer_label,
                'name'      => $r->signer_name,
                'position'  => $r->signer_position ?? '—',
                'signature' => $signatureDataUri,
            ];
        })->toArray();
    }

    /** @deprecated Gunakan hasAnySlot() */
    public static function hasSnapshot(string $type, string $id): bool
    {
        return static::hasAnySlot($type, $id);
    }
}