<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokOpname extends Model
{
    protected $table = 'stok_opname';

    protected $fillable = [
        'nomor', 'warehouse_id', 'status', 'tipe', 'no_referensi',
        'keterangan', 'alasan_penolakan',
        'dibuat_oleh', 'disetujui_oleh',
        'diajukan_at', 'disetujui_at', 'tanggal_opname',
    ];

    protected $casts = [
        'diajukan_at'   => 'datetime',
        'disetujui_at'  => 'datetime',
        'tanggal_opname' => 'date',
    ];

    public function warehouse(): BelongsTo  { return $this->belongsTo(Warehouse::class); }
    public function dibuatOleh(): BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function disetujuiOleh(): BelongsTo { return $this->belongsTo(User::class, 'disetujui_oleh'); }
    public function items(): HasMany        { return $this->hasMany(StokOpnameItem::class); }
}
