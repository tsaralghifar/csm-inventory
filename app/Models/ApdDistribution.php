<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ApdDistribution extends Model {
    protected $fillable = ['distribution_date','employee_id','item_id','warehouse_id','qty','size','brand','handed_by','approved_by','notes','created_by'];
    protected $casts = ['distribution_date'=>'date','qty'=>'decimal:2'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator() { return $this->belongsTo(User::class,'created_by'); }
}
