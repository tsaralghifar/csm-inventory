<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpnameItem extends Model
{
    protected $table = 'stok_opname_items';

    protected $fillable = [
        'stok_opname_id', 'item_id',
        'qty_sistem', 'qty_fisik', 'keterangan',
    ];

    protected $casts = [
        'qty_sistem' => 'decimal:2',
        'qty_fisik'  => 'decimal:2',
        'selisih'    => 'decimal:2',
    ];

    protected $appends = ['selisih'];

    public function getSelisihAttribute(): float
    {
        return (float) $this->qty_fisik - (float) $this->qty_sistem;
    }

    public function stokOpname(): BelongsTo { return $this->belongsTo(StokOpname::class); }
    public function item(): BelongsTo       { return $this->belongsTo(Item::class); }
}
