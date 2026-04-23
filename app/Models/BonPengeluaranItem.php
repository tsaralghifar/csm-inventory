<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonPengeluaranItem extends Model
{
    protected $table = 'bon_pengeluaran_items';

    protected $fillable = [
        'bon_pengeluaran_id', 'item_id', 'nama_barang', 'qty', 'satuan', 'keterangan',
    ];

    protected $casts = ['qty' => 'decimal:2'];

    public function bonPengeluaran()
    {
        return $this->belongsTo(BonPengeluaran::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
