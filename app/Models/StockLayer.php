<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLayer extends Model
{
    protected $table = 'stock_layers';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'qty_awal',
        'qty_sisa',
        'harga_satuan',
        'tanggal_masuk',
        'source_type',
        'reference_no',
        'parent_layer_id',
        'created_by',
    ];

    protected $casts = [
        'qty_awal'      => 'decimal:2',
        'qty_sisa'      => 'decimal:2',
        'harga_satuan'  => 'decimal:2',
        'tanggal_masuk' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentLayer()
    {
        return $this->belongsTo(StockLayer::class, 'parent_layer_id');
    }

    public function childLayers()
    {
        return $this->hasMany(StockLayer::class, 'parent_layer_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Layer yang masih punya sisa stok (belum habis).
     */
    public function scopeAvailable($query)
    {
        return $query->where('qty_sisa', '>', 0);
    }

    /**
     * Ambil layer FIFO: urut dari tanggal_masuk terlama, lalu id terkecil.
     */
    public function scopeFifo($query)
    {
        return $query->orderBy('tanggal_masuk', 'asc')
                     ->orderBy('id', 'asc');
    }

    /**
     * Filter per item dan gudang.
     */
    public function scopeForStock($query, int $itemId, int $warehouseId)
    {
        return $query->where('item_id', $itemId)
                     ->where('warehouse_id', $warehouseId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Apakah layer ini sudah habis.
     */
    public function isEmpty(): bool
    {
        return (float) $this->qty_sisa <= 0;
    }

    /**
     * Label source_type yang ramah dibaca.
     */
    public function getSourceLabel(): string
    {
        return match ($this->source_type) {
            'po'       => 'Purchase Order',
            'import'   => 'Import Saldo Awal',
            'transfer' => 'Transfer Barang',
            'opname'   => 'Stok Opname',
            default    => ucfirst($this->source_type),
        };
    }
}
