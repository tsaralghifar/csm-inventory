<?php

namespace App\Services;

use App\Models\ItemStock;
use App\Models\StockLayer;
use App\Models\StockMovement;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class StokOpnameService
{
    /**
     * Ambil daftar Stok Opname dengan filter.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = StokOpname::with(['warehouse', 'dibuatOleh', 'disetujuiOleh'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->status)       $query->where('status', $request->status);
        if ($request->date_from)    $query->whereDate('tanggal_opname', '>=', $request->date_from);
        if ($request->date_to)      $query->whereDate('tanggal_opname', '<=', $request->date_to);

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Buat Stok Opname baru beserta item-itemnya.
     * Field mengikuti skema model: nomor, tipe, no_referensi, tanggal_opname, dll.
     */
    public function create(array $validated, int $userId): StokOpname
    {
        return DB::transaction(function () use ($validated, $userId) {
            $dateStr = now()->format('Ymd');
            $prefix  = "ADJ-{$dateStr}-";
            $last    = StokOpname::where('nomor', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByRaw("CAST(SUBSTRING(nomor FROM " . (strlen($prefix) + 1) . ") AS INTEGER) DESC")
                ->value('nomor');
            $next  = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
            $nomor = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);

            $opname = StokOpname::create([
                'nomor'          => $nomor,
                'warehouse_id'   => $validated['warehouse_id'],
                'tipe'           => $validated['tipe'],
                'no_referensi'   => $validated['no_referensi'],
                'keterangan'     => $validated['keterangan'] ?? null,
                'tanggal_opname' => $validated['tanggal_opname'],
                'status'         => 'draft',
                'dibuat_oleh'    => $userId,
            ]);

            foreach ($validated['items'] as $item) {
                $systemQty = ItemStock::where('item_id', $item['item_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->value('qty') ?? 0;

                StokOpnameItem::create([
                    'stok_opname_id' => $opname->id,
                    'item_id'        => $item['item_id'],
                    'qty_sistem'     => $systemQty,
                    'qty_fisik'      => $item['qty_fisik'],
                    'keterangan'     => $item['keterangan'] ?? null,
                ]);
            }

            return $opname->load('items.item', 'warehouse', 'dibuatOleh');
        });
    }

    /**
     * Update Stok Opname draft (items di-replace).
     */
    public function update(StokOpname $opname, array $validated): StokOpname
    {
        if ($opname->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen draft yang bisa diedit.',
            ]);
        }

        return DB::transaction(function () use ($opname, $validated) {
            $opname->update([
                'tipe'           => $validated['tipe'],
                'no_referensi'   => $validated['no_referensi'],
                'keterangan'     => $validated['keterangan'] ?? null,
                'tanggal_opname' => $validated['tanggal_opname'],
            ]);

            $opname->items()->delete();

            foreach ($validated['items'] as $item) {
                $systemQty = ItemStock::where('item_id', $item['item_id'])
                    ->where('warehouse_id', $opname->warehouse_id)
                    ->value('qty') ?? 0;

                StokOpnameItem::create([
                    'stok_opname_id' => $opname->id,
                    'item_id'        => $item['item_id'],
                    'qty_sistem'     => $systemQty,
                    'qty_fisik'      => $item['qty_fisik'],
                    'keterangan'     => $item['keterangan'] ?? null,
                ]);
            }

            return $opname->fresh()->load('items.item', 'warehouse', 'dibuatOleh');
        });
    }

    /**
     * Approve Stok Opname → adjust stok sesuai hasil fisik.
     */
    public function approve(StokOpname $opname, StockService $stockService, int $userId): StokOpname
    {
        if ($opname->status !== 'menunggu_approval') {
            throw ValidationException::withMessages([
                'status' => 'Dokumen tidak dalam status menunggu approval.',
            ]);
        }

        DB::transaction(function () use ($opname, $stockService, $userId) {
            $opname->load('items.item');

            $adjIndex = 1;
            foreach ($opname->items as $row) {
                $selisih = (float) $row->qty_fisik - (float) $row->qty_sistem;
                if ($selisih == 0) continue;

                $refNo = $opname->nomor . '-' . str_pad($adjIndex, 3, '0', STR_PAD_LEFT);

                $stockService->adjustment([
                    'item_id'       => $row->item_id,
                    'warehouse_id'  => $opname->warehouse_id,
                    'qty'           => abs($selisih),
                    'type'          => $selisih > 0 ? 'in' : 'out',
                    'notes'         => "[{$opname->tipe}] Ref: {$opname->no_referensi} | Opname: {$opname->nomor}",
                    'movement_date' => $opname->tanggal_opname->format('Y-m-d'),
                    'reference_no'  => $refNo,
                ], $userId);

                // ── Sesuaikan FIFO layers ────────────────────────────────────
                if ($selisih > 0) {
                    // Stok bertambah → buat layer baru dengan harga avg saat ini
                    $avgHarga = ItemStock::where('item_id', $row->item_id)
                        ->where('warehouse_id', $opname->warehouse_id)
                        ->value('avg_price') ?? 0;

                    StockLayer::create([
                        'item_id'       => $row->item_id,
                        'warehouse_id'  => $opname->warehouse_id,
                        'qty_awal'      => abs($selisih),
                        'qty_sisa'      => abs($selisih),
                        'harga_satuan'  => $avgHarga,
                        'tanggal_masuk' => $opname->tanggal_opname->format('Y-m-d'),
                        'source_type'   => 'opname',
                        'reference_no'  => $opname->nomor,
                        'created_by'    => $userId,
                    ]);
                } else {
                    // Stok berkurang → consume layer FIFO dari yang terlama
                    $qtyRemaining = abs($selisih);
                    $layers = StockLayer::forStock($row->item_id, $opname->warehouse_id)
                        ->available()->fifo()->lockForUpdate()->get();

                    foreach ($layers as $layer) {
                        if ($qtyRemaining <= 0) break;
                        $kurangi = min((float) $layer->qty_sisa, $qtyRemaining);
                        $layer->qty_sisa -= $kurangi;
                        $layer->save();
                        $qtyRemaining -= $kurangi;
                    }
                }

                $adjIndex++;
            }

            $opname->update([
                'status'         => 'disetujui',
                'disetujui_oleh' => $userId,
                'disetujui_at'   => now(),
            ]);
        });

        return $opname->fresh()->load('items.item', 'warehouse', 'disetujuiOleh');
    }
}