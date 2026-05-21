<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ReturBarang;
use App\Models\ReturBarangItem;
use App\Models\StockLayer;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ReturBarangService
{
    /**
     * Ambil daftar Retur Barang dengan filter.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = ReturBarang::with(['purchaseOrder', 'warehouse', 'creator'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->status)    $query->where('status', $request->status);
        if ($request->search)    $query->where('retur_number', 'ilike', "%{$request->search}%");
        if ($request->date_from) $query->whereDate('retur_date', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('retur_date', '<=', $request->date_to);

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Buat Retur Barang baru beserta item-nya.
     */
    public function store(array $validated, int $userId): ReturBarang
    {
        return DB::transaction(function () use ($validated, $userId) {
            $retur = ReturBarang::create([
                'retur_number'      => ReturBarang::generateNumber(),
                'purchase_order_id' => $validated['purchase_order_id'],
                'warehouse_id'      => $validated['warehouse_id'],
                'vendor_name'       => $validated['vendor_name'],
                'vendor_contact'    => $validated['vendor_contact'] ?? null,
                'retur_date'        => $validated['retur_date'],
                'alasan'            => $validated['alasan'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'status'            => 'draft',
                'created_by'        => $userId,
            ]);

            foreach ($validated['items'] as $item) {
                ReturBarangItem::create([
                    'retur_barang_id'        => $retur->id,
                    'item_id'                => $item['item_id'] ?? null,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'nama_barang'            => $item['nama_barang'],
                    'part_number'            => $item['part_number'] ?? null,
                    'kode_unit'              => $item['kode_unit'] ?? null,
                    'tipe_unit'              => $item['tipe_unit'] ?? null,
                    'qty'                    => $item['qty'],
                    'satuan'                 => $item['satuan'],
                    'harga_satuan'           => $item['harga_satuan'] ?? 0,
                    'jenis'                  => $item['jenis'],
                    'alasan_item'            => $item['alasan_item'] ?? null,
                ]);
            }

            return $retur->load('items.item', 'purchaseOrder', 'warehouse', 'creator');
        });
    }

    /**
     * Konfirmasi retur:
     * - returnable  → kurangi stok gudang + catat StockMovement
     * - non_returnable → tandai item sebagai salah beli di master
     */
    public function confirm(ReturBarang $retur, int $userId): ReturBarang
    {
        if ($retur->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Retur ini sudah dikonfirmasi sebelumnya.',
            ]);
        }

        DB::transaction(function () use ($retur, $userId) {
            foreach ($retur->items as $returItem) {
                if ($returItem->jenis === 'returnable') {
                    $this->processReturable($returItem, $retur, $userId);
                } else {
                    $this->processNonReturnable($returItem, $retur);
                }
            }

            $retur->update([
                'status'       => 'confirmed',
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);
        });

        return $retur->fresh()->load('items.item', 'purchaseOrder', 'warehouse', 'creator', 'confirmer');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Kurangi stok untuk item returnable dan catat mutasi.
     */
    private function processReturable(ReturBarangItem $returItem, ReturBarang $retur, int $userId): void
    {
        if (!$returItem->item_id) return;

        $stock = ItemStock::firstOrCreate(
            ['item_id' => $returItem->item_id, 'warehouse_id' => $retur->warehouse_id],
            ['qty' => 0, 'qty_reserved' => 0]
        );

        $qtyBefore = (float) $stock->qty;
        $qty       = (float) $returItem->qty;

        if ($qtyBefore < $qty) {
            throw ValidationException::withMessages([
                'qty' => "Stok tidak cukup untuk retur barang \"{$returItem->nama_barang}\". Stok: {$qtyBefore}, Retur: {$qty}",
            ]);
        }

        $stock->qty          = $qtyBefore - $qty;
        $stock->last_updated = now();
        $stock->save();

        // ── Kurangi FIFO layer dari yang terakhir masuk (LIFO untuk retur) ───
        // Logika: retur biasanya dari pembelian terakhir yang bermasalah
        $qtyRemaining = $qty;
        $layers = StockLayer::forStock($returItem->item_id, $retur->warehouse_id)
            ->available()
            ->orderBy('tanggal_masuk', 'desc')  // dari yang terbaru
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($qtyRemaining <= 0) break;
            $kurangi = min((float) $layer->qty_sisa, $qtyRemaining);
            $layer->qty_sisa -= $kurangi;
            $layer->save();
            $qtyRemaining -= $kurangi;
        }

        StockMovement::create([
            'reference_no'      => $this->generateReturRefNo(),
            'type'              => 'out',
            'item_id'           => $returItem->item_id,
            'from_warehouse_id' => $retur->warehouse_id,
            'qty'               => $qty,
            'qty_before'        => $qtyBefore,
            'qty_after'         => $stock->qty,
            'price'             => $returItem->harga_satuan,
            'po_number'         => $retur->purchaseOrder->po_number,
            'notes'             => "Retur ke vendor: {$retur->vendor_name} | {$retur->retur_number}",
            'movement_date'     => $retur->retur_date,
            'moveable_type'     => ReturBarang::class,
            'moveable_id'       => $retur->id,
            'created_by'        => $userId,
        ]);
    }

    /**
     * Tandai item sebagai salah beli di master barang.
     */
    private function processNonReturnable(ReturBarangItem $returItem, ReturBarang $retur): void
    {
        if (!$returItem->item_id) return;

        Item::where('id', $returItem->item_id)->update([
            'is_salah_beli'    => true,
            'salah_beli_notes' => "Ditandai via Retur {$retur->retur_number}: "
                                  . ($returItem->alasan_item ?? $retur->alasan ?? '-'),
        ]);
    }

    /**
     * Generate reference number untuk StockMovement retur dengan database lock.
     */
    private function generateReturRefNo(): string
    {
        $prefix = 'RET-' . now()->format('Ymd') . '-';

        // Scope ke moveable_type ReturBarang agar tidak bentrok dengan
        // reference_no lama dari controller sebelum refactor (yang memakai
        // prefix sama tapi di-insert langsung tanpa moveable_type).
        $lastRef = StockMovement::lockForUpdate()
            ->where('reference_no', 'like', "{$prefix}%")
            ->where('moveable_type', ReturBarang::class)
            ->orderByRaw('CAST(SUBSTRING(reference_no FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('reference_no');

        $lastNumber = $lastRef ? (int) substr($lastRef, strlen($prefix)) : 0;

        return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }
}