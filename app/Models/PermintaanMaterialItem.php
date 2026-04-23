<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermintaanMaterialItem extends Model
{
    protected $table = 'permintaan_material_items';

    protected $fillable = [
        'permintaan_material_id', 'item_id',
        'part_number', 'nama_barang', 'kode_unit', 'tipe_unit',
        'qty', 'satuan', 'keterangan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function permintaan()
    {
        return $this->belongsTo(PermintaanMaterial::class, 'permintaan_material_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}