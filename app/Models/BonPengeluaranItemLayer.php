<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonPengeluaranItemLayer extends Model
{
    protected $table = 'bon_pengeluaran_item_layers';

    protected $fillable = [
        'bon_pengeluaran_item_id',
        'stock_layer_id',
        'qty',
        'harga_satuan',
        'nilai',
        'tanggal_masuk',
        'source_type',
        'reference_no',
    ];

    protected $casts = [
        'qty'          => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'nilai'        => 'decimal:2',
        'tanggal_masuk'=> 'date',
    ];

    public function bonPengeluaranItem()
    {
        return $this->belongsTo(BonPengeluaranItem::class);
    }

    public function stockLayer()
    {
        return $this->belongsTo(StockLayer::class);
    }

    public function getSourceLabel(): string
    {
        return match ($this->source_type) {
            'po'       => 'Purchase Order',
            'import'   => 'Saldo Awal',
            'transfer' => 'Transfer',
            'opname'   => 'Opname',
            default    => ucfirst($this->source_type ?? '-'),
        };
    }
}
