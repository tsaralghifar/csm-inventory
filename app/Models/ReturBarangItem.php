<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturBarangItem extends Model
{
    protected $table = 'retur_barang_items';

    protected $fillable = [
        'retur_barang_id', 'item_id', 'purchase_order_item_id',
        'nama_barang', 'part_number', 'kode_unit', 'tipe_unit',
        'qty', 'satuan', 'harga_satuan',
        'jenis', 'alasan_item',
    ];

    protected $casts = [
        'qty'          => 'decimal:2',
        'harga_satuan' => 'decimal:2',
    ];

    public function returBarang()
    {
        return $this->belongsTo(ReturBarang::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
