<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    // Hanya pakai created_at, tidak ada updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'user_role',
        'action', 'module', 'description',
        'auditable_type', 'auditable_id',
        'old_values', 'new_values',
        'ip_address', 'user_agent', 'url', 'method',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Static helper ───────────────────────────────────────────────────

    /**
     * Catat aktivitas ke audit_logs.
     *
     * Contoh pemakaian:
     *   AuditLog::record('create', 'items', 'Barang baru ditambahkan: Oli SAE 40', $item, null, $item->toArray());
     *   AuditLog::record('update', 'po', 'Status PO berubah: draft → sent', $po, ['status'=>'draft'], ['status'=>'sent']);
     *   AuditLog::record('login',  'auth', 'Login berhasil');
     */
    public static function record(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
    ): void {
        // Jangan catat jika audit sedang di-suppress (misal: seeder/command)
        if (static::$suppressAudit) {
            return;
        }

        $request = request();
        $user    = $request->user();

        static::create([
            'user_id'        => $user?->id,
            'user_name'      => $user?->name,
            'user_role'      => $user?->roles?->first()?->name,
            'action'         => $action,
            'module'         => $module,
            'description'    => $description,
            'auditable_type' => $subject ? get_class($subject) : null,
            'auditable_id'   => $subject?->getKey(),
            'old_values'     => $old,
            'new_values'     => $new,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'url'            => $request->fullUrl(),
            'method'         => $request->method(),
        ]);
    }

    // ─── Suppress (untuk seeder / command / test) ─────────────────────────

    protected static bool $suppressAudit = false;

    /**
     * Jalankan callback tanpa mencatat audit log.
     * Berguna saat seeder, import massal, atau backfill.
     *
     * Contoh:
     *   AuditLog::withoutAudit(fn() => Item::create([...]));
     */
    public static function withoutAudit(callable $callback): mixed
    {
        static::$suppressAudit = true;
        try {
            return $callback();
        } finally {
            static::$suppressAudit = false;
        }
    }

    /** Cek apakah audit sedang di-suppress. */
    public static function isSuppressed(): bool
    {
        return static::$suppressAudit;
    }

    // ─── Label helpers ───────────────────────────────────────────────────

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'create'   => 'Buat',
            'update'   => 'Perbarui',
            'delete'   => 'Hapus',
            'login'    => 'Login',
            'logout'   => 'Logout',
            'export'   => 'Export',
            'approve'  => 'Approve',
            'reject'   => 'Tolak',
            'dispatch' => 'Kirim',
            'receive'  => 'Terima',
            'reset'    => 'Reset Password',
            default    => ucfirst($action),
        };
    }

    public static function actionBadgeClass(string $action): string
    {
        return match ($action) {
            'create'             => 'success',
            'update'             => 'primary',
            'delete'             => 'danger',
            'login', 'logout'    => 'secondary',
            'approve', 'receive' => 'info',
            'reject'             => 'warning',
            'export'             => 'dark',
            default              => 'secondary',
        };
    }
}