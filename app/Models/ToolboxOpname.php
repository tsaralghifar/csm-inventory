<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ToolboxOpname extends Model {
    protected $fillable = ['opname_number','opname_date','employee_id','warehouse_id','status','notes','created_by'];
    protected $casts = ['opname_date'=>'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function items() { return $this->hasMany(ToolboxOpnameItem::class); }
    public function creator() { return $this->belongsTo(User::class,'created_by'); }
    public static function generateNumber(): string {
        $date = now()->format('Ym');
        $last = static::where('opname_number','like',"TBX-{$date}-%")->count();
        return sprintf('TBX-%s-%03d', $date, $last + 1);
    }
}

class ToolboxOpnameItem extends Model {
    protected $fillable = ['toolbox_opname_id','item_id','item_code','qty','unit','condition','notes'];
    protected $casts = ['qty'=>'decimal:2'];
    public function opname() { return $this->belongsTo(ToolboxOpname::class,'toolbox_opname_id'); }
    public function item() { return $this->belongsTo(Item::class); }
}

class AuditLog extends Model {
    public $timestamps = false;
    protected $fillable = ['user_id','action','model_type','model_id','old_values','new_values','ip_address','user_agent','created_at'];
    protected $casts = ['old_values'=>'array','new_values'=>'array','created_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}
