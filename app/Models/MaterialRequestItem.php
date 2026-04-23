<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestItem extends Model
{
    protected $fillable = [
        'material_request_id', 'item_id', 'qty_request', 'qty_approved', 'qty_sent', 'qty_received', 'notes',
    ];

    protected $casts = [
        'qty_request' => 'decimal:2',
        'qty_approved' => 'decimal:2',
        'qty_sent' => 'decimal:2',
        'qty_received' => 'decimal:2',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
