<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceAnomaly extends Model
{
    protected $table = 'price_anomalies';

    protected $fillable = [
        'item_id', 'warehouse_id', 'anomaly_type', 'severity',
        'value_before', 'value_after', 'change_pct',
        'reference_no', 'supplier_name', 'meta',
        'is_read', 'read_at', 'created_by',
    ];

    protected $casts = [
        'value_before' => 'decimal:2',
        'value_after'  => 'decimal:2',
        'change_pct'   => 'decimal:2',
        'meta'         => 'array',
        'is_read'      => 'boolean',
        'read_at'      => 'datetime',
    ];

    public function item()      { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }

    public function getTypeLabel(): string
    {
        return match ($this->anomaly_type) {
            'price_spike'          => 'Lonjakan Harga',
            'consecutive_increase' => 'Kenaikan Berturut-turut',
            'po_vs_receive'        => 'Selisih Harga PO vs Terima',
            'budget_exceeded'      => 'Budget Terlampaui',
            default                => ucfirst($this->anomaly_type),
        };
    }

    public function getSeverityLabel(): string
    {
        return match ($this->severity) {
            'info'     => 'Info',
            'warning'  => 'Waspada',
            'critical' => 'Kritis',
            default    => $this->severity,
        };
    }

    public function scopeUnread($q)   { return $q->where('is_read', false); }
    public function scopeCritical($q) { return $q->where('severity', 'critical'); }
}
