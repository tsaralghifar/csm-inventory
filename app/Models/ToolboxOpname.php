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