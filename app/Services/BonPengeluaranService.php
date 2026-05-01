<?php

namespace App\Services;

use App\Models\BonPengeluaran;
use App\Models\BonPengeluaranItem;
use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class BonPengeluaranService
{
    /**
     * Ambil daftar Bon Pengeluaran dengan filter.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = BonPengeluaran::with(['materialRequest', 'permintaanMaterial', 'warehouse', 'creator', 'approver'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->status)    $query->where('status', $request->status);
        if ($request->search)    $query->where('bon_number', 'ilike', "%{$request->search}%");
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Buat Bon Pengeluaran baru.
     * Bisa dari MR, PM, atau manual (StokHO/StokSite).
     * Jika auto_issue = true → langsung issue stok.
     */
    public function create(array $validated, int $userId): BonPengeluaran
    {
        return DB::transaction(function () use ($validated, $userId) {
            $bon = BonPengeluaran::create([
                'bon_number'             => BonPengeluaran::generateNumber(),
                'material_request_id'    => $validated['material_request_id'] ?? null,
                'permintaan_material_id' => $validated['permintaan_material_id'] ?? null,
                'warehouse_id'           => $validated['warehouse_id'],
                'created_by'             => $userId,
                'status'                 => 'draft',
                'received_by'            => $validated['received_by'],
                'issue_date'             => $validated['issue_date'],
                'notes'                  => $validated['notes'] ?? null,
                'unit_code'              => $validated['unit_code'] ?? null,
                'unit_type'              => $validated['unit_type'] ?? null,
                'hm_km'                  => $validated['hm_km'] ?? null,
                'mechanic'               => $validated['mechanic'] ?? null,
                'po_number'              => $validated['po_number'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                BonPengeluaranItem::create(array_merge($item, ['bon_pengeluaran_id' => $bon->id]));
            }

            if (!empty($validated['auto_issue'])) {
                $this->issueStock($bon, $userId);
            }

            return $bon->load('items', 'warehouse', 'creator');
        });
    }

    /**
     * Approve Bon Pengeluaran → kurangi stok.
     */
    public function approve(BonPengeluaran $bon, int $userId): BonPengeluaran
    {
        if ($bon->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Bon Pengeluaran sudah pernah diapprove atau diissue.',
            ]);
        }

        DB::transaction(function () use ($bon, $userId) {
            $this->issueStock($bon, $userId);
        });

        return $bon->fresh()->load('items', 'warehouse', 'approver');
    }

    /**
     * Hapus Bon Pengeluaran (hanya status draft).
     */
    public function delete(BonPengeluaran $bon): void
    {
        if ($bon->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya Bon Pengeluaran draft yang bisa dihapus.',
            ]);
        }

        $bon->delete();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Issue stok: kurangi ItemStock dan buat StockMovement untuk setiap item.
     */
    private function issueStock(BonPengeluaran $bon, int $userId): void
    {
        $bon->load('items');

        foreach ($bon->items as $bonItem) {
            if (!$bonItem->item_id) continue;

            $qty = (float) $bonItem->qty;

            $stock = ItemStock::where('item_id', $bonItem->item_id)
                ->where('warehouse_id', $bon->warehouse_id)
                ->first();

            if (!$stock || $stock->qty < $qty) {
                throw ValidationException::withMessages([
                    'stock' => "Stok tidak cukup untuk item: {$bonItem->nama_barang}. " .
                               "Tersedia: " . ($stock?->qty ?? 0) . ", Dibutuhkan: {$qty}",
                ]);
            }

            $qtyBefore = $stock->qty;
            $stock->decrement('qty', $qty);

            StockMovement::create([
                'item_id'          => $bonItem->item_id,
                'from_warehouse_id'=> $bon->warehouse_id,
                'type'             => 'issue_out',
                'qty'              => $qty,
                'qty_before'       => $qtyBefore,
                'qty_after'        => $qtyBefore - $qty,
                'reference_no'     => $bon->bon_number,
                'notes'            => "Bon Pengeluaran: {$bon->bon_number}",
                'moveable_type'    => BonPengeluaran::class,
                'moveable_id'      => $bon->id,
                'movement_date'    => $bon->issue_date,
                'created_by'       => $userId,
            ]);
        }

        $bon->update([
            'status'      => 'issued',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        // Update status MR / PM terkait
        $this->updateSourceStatus($bon);
    }

    private function updateSourceStatus(BonPengeluaran $bon): void
    {
        if ($bon->material_request_id) {
            MaterialRequest::find($bon->material_request_id)
                ?->update(['status' => 'completed']);
        }

        if ($bon->permintaan_material_id) {
            $pm = PermintaanMaterial::with('items')->find($bon->permintaan_material_id);

            if (!$pm) return;

            // Hitung total qty yang sudah dikeluarkan untuk PM ini dari semua bon yang issued
            $qtyIssuedByItem = \App\Models\BonPengeluaranItem::whereHas('bonPengeluaran', function ($q) use ($pm) {
                    $q->where('permintaan_material_id', $pm->id)
                      ->where('status', 'issued');
                })
                ->selectRaw('item_id, nama_barang, SUM(qty) as total_issued')
                ->groupBy('item_id', 'nama_barang')
                ->get()
                ->keyBy('item_id');

            // PM dianggap selesai hanya jika setiap item sudah terpenuhi seluruhnya
            $allFulfilled = $pm->items->every(function ($pmItem) use ($qtyIssuedByItem) {
                $issued = isset($qtyIssuedByItem[$pmItem->item_id])
                    ? (float) $qtyIssuedByItem[$pmItem->item_id]->total_issued
                    : 0;
                return $issued >= (float) $pmItem->qty;
            });

            if ($allFulfilled) {
                $pm->update(['status' => 'completed']);
            } elseif ($pm->status !== 'completed') {
                // Tandai sebagai partial jika belum ada status khusus partial
                $pm->update(['status' => 'partial_issued']);
            }
        }
    }
}