<?php

namespace App\Traits;

use App\Models\AuditLog;

/**
 * Trait Auditable
 *
 * Tambahkan trait ini ke model mana saja yang ingin dicatat otomatis
 * di audit_logs via Observer.
 *
 * Cara pakai di model:
 *   use App\Traits\Auditable;
 *   class Item extends Model {
 *       use Auditable;
 *       protected string $auditModule = 'items';         // wajib
 *       protected array  $auditExclude = ['updated_at']; // opsional
 *   }
 */
trait Auditable
{
    // ─── Boot ────────────────────────────────────────────────────────────────

    public static function bootAuditable(): void
    {
        // CREATING — belum ada data lama
        static::creating(function ($model) {
            $model->_auditOld = null;
        });

        // CREATED — simpan new_values
        static::created(function ($model) {
            AuditLog::record(
                action:      'create',
                module:      $model->getAuditModule(),
                description: $model->auditDescription('create'),
                subject:     $model,
                old:         null,
                new:         $model->getAuditAttributes(),
            );
        });

        // UPDATING — simpan old_values sebelum disimpan
        static::updating(function ($model) {
            $model->_auditOld = $model->getOriginal();
        });

        // UPDATED — bandingkan old vs new, hanya field yang berubah
        static::updated(function ($model) {
            $old = $model->filterAuditAttributes($model->_auditOld ?? []);
            $new = $model->filterAuditAttributes($model->getChanges());

            // Jangan catat jika tidak ada perubahan yang relevan
            if (empty($new)) return;

            AuditLog::record(
                action:      'update',
                module:      $model->getAuditModule(),
                description: $model->auditDescription('update'),
                subject:     $model,
                old:         array_intersect_key($old, $new), // old hanya field yg berubah
                new:         $new,
            );
        });

        // DELETING — simpan old_values sebelum dihapus
        static::deleting(function ($model) {
            $model->_auditOld = $model->getAuditAttributes();
        });

        // DELETED
        static::deleted(function ($model) {
            AuditLog::record(
                action:      'delete',
                module:      $model->getAuditModule(),
                description: $model->auditDescription('delete'),
                subject:     $model,
                old:         $model->_auditOld,
                new:         null,
            );
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Nama modul untuk audit_logs.module. Override di model jika perlu. */
    public function getAuditModule(): string
    {
        return property_exists($this, 'auditModule')
            ? $this->auditModule
            : strtolower(class_basename($this)) . 's';
    }

    /** Kolom-kolom yang TIDAK dicatat (selain updated_at default). */
    protected function getAuditExclude(): array
    {
        $base = ['updated_at', 'created_at', 'deleted_at', 'remember_token', 'password'];
        return array_merge(
            $base,
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );
    }

    /** Ambil semua atribut model, singkirkan kolom yang di-exclude. */
    public function getAuditAttributes(): array
    {
        return $this->filterAuditAttributes($this->getAttributes());
    }

    public function filterAuditAttributes(array $attrs): array
    {
        return array_diff_key($attrs, array_flip($this->getAuditExclude()));
    }

    /** Deskripsi human-readable. Override di model untuk kustomisasi. */
    public function auditDescription(string $action): string
    {
        $label = match ($action) {
            'create' => 'dibuat',
            'update' => 'diperbarui',
            'delete' => 'dihapus',
            default  => $action,
        };

        $name = $this->getAuditDisplayName();
        return ucfirst(class_basename($this)) . " {$name} {$label}";
    }

    /** Nama tampilan entitas untuk deskripsi. Override di model. */
    public function getAuditDisplayName(): string
    {
        foreach (['name', 'title', 'number', 'po_number', 'no', 'code', 'email'] as $key) {
            if (!empty($this->attributes[$key])) {
                return "[{$this->attributes[$key]}]";
            }
        }
        return "[ID #{$this->getKey()}]";
    }
}
