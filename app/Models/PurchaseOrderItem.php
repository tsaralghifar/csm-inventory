<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id', 'item_id', 'permintaan_material_item_id', 'qty_pm',
        'nama_barang', 'kode_unit', 'tipe_unit',
        'qty', 'qty_received', 'satuan', 'harga_satuan',
        'diskon_persen', 'diskon_amount', 'total_harga', 'keterangan',
    ];

    protected $casts = [
        'qty'           => 'decimal:2',
        'qty_pm'        => 'decimal:2',
        'qty_received'  => 'decimal:2',
        'harga_satuan'  => 'decimal:2',
        'diskon_persen' => 'decimal:2',
        'diskon_amount' => 'decimal:2',
        'total_harga'   => 'decimal:2',
    ];

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Sisa qty yang belum diterima.
     * Gunakan: $item->qty_remaining
     */
    public function getQtyRemainingAttribute(): float
    {
        return max(0, (float) $this->qty - (float) $this->qty_received);
    }

    /**
     * Apakah item ini sudah diterima seluruhnya.
     */
    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->qty_received >= $this->qty;
    }

    /**
     * Persentase penerimaan (0–100).
     */
    public function getReceiptPercentageAttribute(): float
    {
        if ((float) $this->qty <= 0) return 0;
        return min(100, round(($this->qty_received / $this->qty) * 100, 1));
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function permintaanMaterialItem()
    {
        return $this->belongsTo(PermintaanMaterialItem::class);
    }
}