<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartNumberChangeLog extends Model
{
    protected $fillable = [
        'purchase_order_item_id',
        'purchase_order_id',
        'permintaan_material_item_id',
        'item_id',
        'old_part_number',
        'new_part_number',
        'po_status_at_change',
        'update_master',
        'notes',
        'changed_by',
    ];

    protected $casts = [
        'update_master' => 'boolean',
    ];

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function permintaanMaterialItem(): BelongsTo
    {
        return $this->belongsTo(PermintaanMaterialItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
