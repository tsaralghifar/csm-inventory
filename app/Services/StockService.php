<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Add stock to a warehouse (stock in from supplier)
     */
    public function stockIn(array $data, int $userId): StockMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $itemStock = $this->getOrCreateStock($data['item_id'], $data['warehouse_id']);
            $qtyBefore = (float) $itemStock->qty;
            $qty = (float) $data['qty'];
            $newPrice = (float) ($data['price'] ?? 0);

            // Hitung Average Cost (AVCO)
            if ($newPrice > 0) {
                $oldAvg = (float) $itemStock->avg_price;
                $newAvg = $qtyBefore > 0
                    ? (($qtyBefore * $oldAvg) + ($qty * $newPrice)) / ($qtyBefore + $qty)
                    : $newPrice;

                $itemStock->avg_price = $newAvg;

                // Update harga di master item
                \App\Models\Item::where('id', $data['item_id'])
                    ->update(['price' => $newAvg]);

                // Catat riwayat harga
                \App\Models\ItemPriceHistory::create([
                    'item_id'           => $data['item_id'],
                    'warehouse_id'      => $data['warehouse_id'],
                    'purchase_price'    => $newPrice,
                    'avg_price_before'  => $oldAvg,
                    'avg_price_after'   => $newAvg,
                    'qty_received'      => $qty,
                    'reference_no'      => $data['po_number'] ?? null,
                    'source_type'       => 'stock_in',
                    'created_by'        => $userId,
                    'transaction_date'  => $data['movement_date'] ?? today(),
                ]);
            }

            $itemStock->qty = $qtyBefore + $qty;
            $itemStock->last_updated = now();
            $itemStock->save();

            return $this->createMovement([
                'type' => 'in',
                'item_id' => $data['item_id'],
                'to_warehouse_id' => $data['warehouse_id'],
                'qty' => $qty,
                'qty_before' => $qtyBefore,
                'qty_after' => $itemStock->qty,
                'price' => $data['price'] ?? 0,
                'po_number' => $data['po_number'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'movement_date' => $data['movement_date'] ?? today(),
            ], $userId);
        });
    }

    /**
     * Remove stock from warehouse (direct stock out)
     */
    public function stockOut(array $data, int $userId): StockMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $itemStock = $this->getOrCreateStock($data['item_id'], $data['warehouse_id']);
            $qtyBefore = (float) $itemStock->qty;
            $qty = (float) $data['qty'];

            // Prevent minus stock
            if ($qtyBefore < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak cukup. Stok tersedia: {$qtyBefore}, diminta: {$qty}",
                ]);
            }

            $itemStock->qty = $qtyBefore - $qty;
            $itemStock->last_updated = now();
            $itemStock->save();

            return $this->createMovement([
                'type' => 'out',
                'item_id' => $data['item_id'],
                'from_warehouse_id' => $data['warehouse_id'],
                'qty' => $qty,
                'qty_before' => $qtyBefore,
                'qty_after' => $itemStock->qty,
                'unit_code' => $data['unit_code'] ?? null,
                'unit_type' => $data['unit_type'] ?? null,
                'hm_km' => $data['hm_km'] ?? null,
                'po_number' => $data['po_number'] ?? null,
                'mechanic' => $data['mechanic'] ?? null,
                'site_name' => $data['site_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'movement_date' => $data['movement_date'] ?? today(),
                'moveable_type' => $data['moveable_type'] ?? 'manual',
                'moveable_id' => $data['moveable_id'] ?? 0,
            ], $userId);
        });
    }

    /**
     * Transfer stock from one warehouse to another
     */
    public function transfer(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $fromStock = $this->getOrCreateStock($data['item_id'], $data['from_warehouse_id']);
            $qtyBeforeFrom = (float) $fromStock->qty;
            $qty = (float) $data['qty'];

            // Validate stock availability
            $available = $qtyBeforeFrom - (float) $fromStock->qty_reserved;
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak cukup. Stok tersedia: {$available}, diminta: {$qty}",
                ]);
            }

            // Deduct from source
            $fromStock->qty -= $qty;
            $fromStock->qty_reserved = max(0, $fromStock->qty_reserved - $qty);
            $fromStock->last_updated = now();
            $fromStock->save();

            // Add to destination
            $toStock = $this->getOrCreateStock($data['item_id'], $data['to_warehouse_id']);
            $qtyBeforeTo = (float) $toStock->qty;
            $toStock->qty += $qty;
            $toStock->last_updated = now();
            $toStock->save();

            $baseData = [
                'item_id' => $data['item_id'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'qty' => $qty,
                'notes' => $data['notes'] ?? null,
                'movement_date' => $data['movement_date'] ?? today(),
                'moveable_type' => $data['moveable_type'] ?? null,
                'moveable_id' => $data['moveable_id'] ?? null,
            ];

            $outMovement = $this->createMovement(array_merge($baseData, [
                'type' => 'transfer_out',
                'qty_before' => $qtyBeforeFrom,
                'qty_after' => $fromStock->qty,
            ]), $userId);

            $inMovement = $this->createMovement(array_merge($baseData, [
                'type' => 'transfer_in',
                'qty_before' => $qtyBeforeTo,
                'qty_after' => $toStock->qty,
            ]), $userId);

            return ['out' => $outMovement, 'in' => $inMovement];
        });
    }

    /**
     * Reserve stock for approved MR (prevent over-commitment)
     */
    public function reserveStock(int $itemId, int $warehouseId, float $qty): void
    {
        DB::transaction(function () use ($itemId, $warehouseId, $qty) {
            $stock = $this->getOrCreateStock($itemId, $warehouseId);
            $available = (float) $stock->qty - (float) $stock->qty_reserved;
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak mencukupi untuk di-reserve",
                ]);
            }
            $stock->qty_reserved += $qty;
            $stock->save();
        });
    }

    /**
     * Release reserved stock (when MR rejected/cancelled)
     */
    public function releaseReserve(int $itemId, int $warehouseId, float $qty): void
    {
        $stock = $this->getOrCreateStock($itemId, $warehouseId);
        $stock->qty_reserved = max(0, (float) $stock->qty_reserved - $qty);
        $stock->save();
    }

    /**
     * Stock adjustment (opname)
     */
    public function adjust(int $itemId, int $warehouseId, float $newQty, string $notes, int $userId): StockMovement
    {
        return DB::transaction(function () use ($itemId, $warehouseId, $newQty, $notes, $userId) {
            $stock = $this->getOrCreateStock($itemId, $warehouseId);
            $qtyBefore = (float) $stock->qty;
            $stock->qty = $newQty;
            $stock->last_updated = now();
            $stock->save();

            return $this->createMovement([
                'type' => 'opname',
                'item_id' => $itemId,
                'from_warehouse_id' => $warehouseId,
                'qty' => abs($newQty - $qtyBefore),
                'qty_before' => $qtyBefore,
                'qty_after' => $newQty,
                'notes' => $notes,
                'movement_date' => today(),
            ], $userId);
        });
    }

    /**
     * Get or create item_stocks record
     */
    private function getOrCreateStock(int $itemId, int $warehouseId): ItemStock
    {
        return ItemStock::firstOrCreate(
            ['item_id' => $itemId, 'warehouse_id' => $warehouseId],
            ['qty' => 0, 'qty_reserved' => 0]
        );
    }

    /**
     * Create stock movement record with auto reference number
     */
    private function createMovement(array $data, int $userId): StockMovement
    {
        $prefix = match($data['type']) {
            'in' => 'IN',
            'out' => 'OUT',
            'transfer_out', 'transfer_in' => 'TRF',
            'adjustment', 'opname' => 'ADJ',
            default => 'MOV',
        };

        $dateStr = now()->format('Ymd');
        $prefixFull = $prefix . '-' . $dateStr . '-';

        $lastRef = StockMovement::lockForUpdate()
            ->where('reference_no', 'like', "{$prefixFull}%")
            ->orderByRaw('CAST(SUBSTRING(reference_no FROM ' . (strlen($prefixFull) + 1) . ') AS INTEGER) DESC')
            ->value('reference_no');

        $lastNumber = $lastRef ? (int) substr($lastRef, strlen($prefixFull)) : 0;
        $refNo = $prefixFull . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return StockMovement::create(array_merge($data, [
            'reference_no' => $refNo,
            'created_by' => $userId,
        ]));
    }

    /**
     * Get stock summary for a warehouse
     */
    public function getWarehouseStockSummary(int $warehouseId): array
    {
        $stocks = ItemStock::with(['item.category'])
            ->where('warehouse_id', $warehouseId)
            ->get();

        return [
            'total_items' => $stocks->count(),
            'total_value' => $stocks->sum(fn($s) => $s->qty * $s->item->price),
            'critical_items' => $stocks->filter(fn($s) => $s->isCritical())->count(),
            'minus_items' => $stocks->filter(fn($s) => $s->isMinus())->count(),
        ];
    }
}