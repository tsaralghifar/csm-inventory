<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratJalanItem extends Model
{
    protected $table = 'surat_jalan_items';

    protected $fillable = [
        'surat_jalan_id', 'purchase_order_item_id', 'nama_barang', 'kode_unit', 'tipe_unit',
        'qty_ordered', 'qty_received', 'satuan', 'harga_satuan',
        'masuk_stok', 'item_id', 'keterangan',
    ];

    protected $casts = [
        'qty_ordered'  => 'decimal:2',
        'qty_received' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'masuk_stok'   => 'boolean',
    ];

    public function suratJalan()
    {
        return $this->belongsTo(SuratJalan::class);
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