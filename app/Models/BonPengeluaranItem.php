<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonPengeluaranItem extends Model
{
    protected $table = 'bon_pengeluaran_items';

    protected $fillable = [
        'bon_pengeluaran_id', 'item_id', 'nama_barang', 'qty', 'satuan', 'keterangan', 'harga_satuan', 'fifo_price',
    ];

    protected $casts = [
        'qty'          => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'fifo_price'   => 'decimal:2',
    ];

    public function bonPengeluaran()
    {
        return $this->belongsTo(BonPengeluaran::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function fifoLayers()
    {
        return $this->hasMany(BonPengeluaranItemLayer::class, 'bon_pengeluaran_item_id')
                    ->orderBy('tanggal_masuk')
                    ->orderBy('id');
    }
}