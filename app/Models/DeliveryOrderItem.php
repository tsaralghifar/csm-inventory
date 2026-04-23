<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryOrderItem extends Model {
    protected $fillable = ['delivery_order_id','item_id','qty_sent','qty_received','notes'];
    protected $casts = ['qty_sent'=>'decimal:2','qty_received'=>'decimal:2'];
    public function deliveryOrder() { return $this->belongsTo(DeliveryOrder::class); }
    public function item() { return $this->belongsTo(Item::class); }
}
