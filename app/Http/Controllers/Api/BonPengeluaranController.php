<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BonPengeluaran;
use App\Models\BonPengeluaranItem;
use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BonPengeluaranController extends Controller
{
    // GET /bon-pengeluaran
    public function index(Request $request)
    {
        $query = BonPengeluaran::with(['materialRequest', 'permintaanMaterial', 'warehouse', 'creator', 'approver'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->status)    $query->where('status', $request->status);
        if ($request->search)    $query->where('bon_number', 'ilike', "%{$request->search}%");
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /bon-pengeluaran
    // Bisa dari MR (material_request_id) atau PM (permintaan_material_id) atau manual dari StokHO/StokSite
    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_request_id'      => 'nullable|exists:material_requests,id',
            'permintaan_material_id'   => 'nullable|exists:permintaan_material,id',
            'warehouse_id'             => 'required|exists:warehouses,id',
            'received_by'              => 'required|string|max:255',
            'issue_date'               => 'required|date',
            'notes'                    => 'nullable|string',
            'unit_code'                => 'nullable|string|max:50',
            'unit_type'                => 'nullable|string|max:100',
            'hm_km'                    => 'nullable|numeric',
            'mechanic'                 => 'nullable|string|max:150',
            'po_number'                => 'nullable|string|max:100',
            'auto_issue'               => 'nullable|boolean',
            'items'                    => 'required|array|min:1',
            'items.*.item_id'          => 'nullable|exists:items,id',
            'items.*.nama_barang'      => 'required|string|max:255',
            'items.*.qty'              => 'required|numeric|min:0.01',
            'items.*.satuan'           => 'required|string|max:50',
            'items.*.keterangan'       => 'nullable|string',
        ]);

        $bon = DB::transaction(function () use ($validated, $request) {
            $bon = BonPengeluaran::create([
                'bon_number'               => BonPengeluaran::generateNumber(),
                'material_request_id'      => $validated['material_request_id'] ?? null,
                'permintaan_material_id'   => $validated['permintaan_material_id'] ?? null,
                'warehouse_id'             => $validated['warehouse_id'],
                'created_by'               => $request->user()->id,
                'status'                   => 'draft',
                'received_by'              => $validated['received_by'],
                'issue_date'               => $validated['issue_date'],
                'notes'                    => $validated['notes'] ?? null,
                'unit_code'                => $validated['unit_code'] ?? null,
                'unit_type'                => $validated['unit_type'] ?? null,
                'hm_km'                    => $validated['hm_km'] ?? null,
                'mechanic'                 => $validated['mechanic'] ?? null,
                'po_number'                => $validated['po_number'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                BonPengeluaranItem::create(array_merge($item, [
                    'bon_pengeluaran_id' => $bon->id,
                ]));
            }

            // Update status sumber dokumen
            if (!empty($validated['material_request_id'])) {
                MaterialRequest::find($validated['material_request_id'])
                    ->update(['status' => 'bon_pengeluaran']);
            }
            if (!empty($validated['permintaan_material_id'])) {
                PermintaanMaterial::find($validated['permintaan_material_id'])
                    ->update(['status' => 'bon_pengeluaran']);
            }

            // Auto-issue jika diminta (dari StokHO/StokSite langsung)
            if (!empty($validated['auto_issue'])) {
                $itemIndex = 1;
                foreach ($bon->items as $bonItem) {
                    $itemId = $bonItem->item_id;
                    if (!$itemId) {
                        $item = \App\Models\Item::where('name', 'ilike', $bonItem->nama_barang)->first();
                        if ($item) { $itemId = $item->id; $bonItem->update(['item_id' => $itemId]); }
                    }
                    if (!$itemId) { $itemIndex++; continue; }

                    $stock = ItemStock::where('item_id', $itemId)->where('warehouse_id', $bon->warehouse_id)->first();
                    if (!$stock || $stock->qty < $bonItem->qty) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'stock' => "Stok tidak cukup untuk: {$bonItem->nama_barang} (tersedia: " . ($stock?->qty ?? 0) . ", diminta: {$bonItem->qty})"
                        ]);
                    }

                    $qtyBefore = $stock->qty;
                    $stock->decrement('qty', $bonItem->qty);

                    $pmNumber = $bon->permintaan_material_id
                        ? optional(\App\Models\PermintaanMaterial::find($bon->permintaan_material_id))->nomor
                        : null;
                    $movNotes = "Bon Pengeluaran: {$bon->bon_number}"
                        . ($pmNumber ? " | PM: {$pmNumber}" : "")
                        . ($bon->received_by ? " | Diterima: {$bon->received_by}" : "");

                    StockMovement::create([
                        'item_id'           => $itemId,
                        'from_warehouse_id' => $bon->warehouse_id,
                        'type'              => 'out',
                        'qty'               => $bonItem->qty,
                        'qty_before'        => $qtyBefore,
                        'qty_after'         => $qtyBefore - $bonItem->qty,
                        'reference_no'      => $bon->bon_number . '-' . str_pad($itemIndex, 3, '0', STR_PAD_LEFT),
                        'notes'             => $movNotes,
                        'unit_code'         => $bon->unit_code,
                        'moveable_type'     => BonPengeluaran::class,
                        'moveable_id'       => $bon->id,
                        'movement_date'     => $bon->issue_date,
                        'created_by'        => $request->user()->id,
                    ]);
                    $itemIndex++;
                }

                $bon->update([
                    'status'      => 'issued',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
            }

            return $bon->load('items.item', 'warehouse', 'creator');
        });

        return response()->json(['success' => true, 'data' => $bon, 'message' => 'Bon Pengeluaran berhasil dibuat'], 201);
    }

    // GET /bon-pengeluaran/{id}
    public function show(BonPengeluaran $bonPengeluaran)
    {
        return response()->json([
            'success' => true,
            'data'    => $bonPengeluaran->load('items.item', 'warehouse', 'creator', 'approver', 'materialRequest', 'permintaanMaterial'),
        ]);
    }

    // POST /bon-pengeluaran/{id}/issue
    public function issue(Request $request, BonPengeluaran $bonPengeluaran)
    {
        if ($bonPengeluaran->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Bon sudah pernah dikeluarkan']);
        }

        DB::transaction(function () use ($bonPengeluaran, $request) {
            $itemIndex = 1;
            foreach ($bonPengeluaran->items as $bonItem) {
                // Cari item_id jika belum ada — cari berdasarkan nama barang
                $itemId = $bonItem->item_id;
                if (!$itemId) {
                    $item = \App\Models\Item::where('name', 'ilike', $bonItem->nama_barang)->first();
                    if ($item) {
                        $itemId = $item->id;
                        $bonItem->update(['item_id' => $itemId]);
                    }
                }

                if (!$itemId) continue; // Barang benar-benar tidak ada di master

                $stock = ItemStock::where('item_id', $itemId)
                    ->where('warehouse_id', $bonPengeluaran->warehouse_id)
                    ->first();

                if (!$stock || $stock->qty < $bonItem->qty) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok tidak cukup untuk barang: {$bonItem->nama_barang} (tersedia: " . ($stock?->qty ?? 0) . ", diminta: {$bonItem->qty})"
                    ]);
                }

                $qtyBefore = $stock->qty;
                $stock->decrement('qty', $bonItem->qty);

                StockMovement::create([
                    'item_id'            => $itemId,
                    'from_warehouse_id'  => $bonPengeluaran->warehouse_id,
                    'type'               => 'out',
                    'qty'                => $bonItem->qty,
                    'qty_before'         => $qtyBefore,
                    'qty_after'          => $qtyBefore - $bonItem->qty,
                    'reference_no'       => $bonPengeluaran->bon_number . '-' . str_pad($itemIndex, 3, '0', STR_PAD_LEFT),
                    'notes'              => "Bon Pengeluaran: {$bonPengeluaran->bon_number}",
                    'moveable_type'      => BonPengeluaran::class,
                    'moveable_id'        => $bonPengeluaran->id,
                    'movement_date'      => $bonPengeluaran->issue_date,
                    'created_by'         => $request->user()->id,
                ]);

                $itemIndex++;
            }

            $bonPengeluaran->update([
                'status'      => 'issued',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            // Update status sumber dokumen ke completed
            if ($bonPengeluaran->material_request_id) {
                MaterialRequest::find($bonPengeluaran->material_request_id)
                    ->update(['status' => 'completed']);
            }
            if ($bonPengeluaran->permintaan_material_id) {
                PermintaanMaterial::find($bonPengeluaran->permintaan_material_id)
                    ->update(['status' => 'completed']);
            }
        });

        return response()->json(['success' => true, 'message' => 'Bon Pengeluaran berhasil dikeluarkan, stok telah dikurangi']);
    }
}