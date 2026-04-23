<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    // GET /purchase-orders
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['materialRequest', 'permintaanMaterials', 'warehouse', 'creator'])
            ->withCount(['items', 'suratJalan'])
            ->orderBy('created_at', 'desc');

        if ($request->status)    $query->where('status', $request->status);
        if ($request->search)    $query->where('po_number', 'ilike', "%{$request->search}%");
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /purchase-orders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_request_id'                  => 'nullable|exists:material_requests,id',
            // Support array PM IDs (many-to-many) dan single ID (backward compat)
            'permintaan_material_ids'              => 'nullable|array|min:1',
            'permintaan_material_ids.*'            => 'exists:permintaan_material,id',
            'permintaan_material_id'               => 'nullable|exists:permintaan_material,id',
            'warehouse_id'                         => 'required|exists:warehouses,id',
            'vendor_name'                          => 'required|string|max:255',
            'vendor_contact'                       => 'nullable|string|max:255',
            'expected_date'                        => 'nullable|date',
            'notes'                                => 'nullable|string',
            'ppn_percent'                          => 'nullable|numeric|min:0|max:100',
            'diskon_persen'                        => 'nullable|numeric|min:0|max:100',
            'items'                                => 'required|array|min:1',
            'items.*.item_id'                      => 'nullable|exists:items,id',
            'items.*.permintaan_material_item_id'  => 'nullable|exists:permintaan_material_items,id',
            'items.*.qty_pm'                       => 'nullable|numeric|min:0',
            'items.*.part_number'                  => 'nullable|string|max:100',
            'items.*.nama_barang'                  => 'required|string|max:255',
            'items.*.kode_unit'                    => 'nullable|string',
            'items.*.tipe_unit'                    => 'nullable|string',
            'items.*.qty'                          => 'required|numeric|min:0.01',
            'items.*.satuan'                       => 'required|string',
            'items.*.harga_satuan'                 => 'nullable|numeric|min:0',
            'items.*.diskon_persen'                => 'nullable|numeric|min:0|max:100',
            'items.*.keterangan'                   => 'nullable|string',
        ]);

        // Normalisasi: gabungkan permintaan_material_ids & permintaan_material_id (single)
        $pmIds = collect($validated['permintaan_material_ids'] ?? []);
        if (!empty($validated['permintaan_material_id'])) {
            $pmIds->push($validated['permintaan_material_id']);
        }
        $pmIds = $pmIds->unique()->values();

        if (empty($validated['material_request_id']) && $pmIds->isEmpty()) {
            throw ValidationException::withMessages([
                'source' => 'Harus ada setidaknya satu Permintaan Material atau Material Request.',
            ]);
        }

        // Validasi status setiap PM
        foreach ($pmIds as $pmId) {
            $pm = PermintaanMaterial::findOrFail($pmId);
            if (!in_array($pm->status, ['approved', 'manager_approved', 'purchasing', 'partial_ordered', 'completed'])) {
                throw ValidationException::withMessages([
                    'status' => "PM {$pm->nomor} harus sudah disetujui sebelum membuat PO.",
                ]);
            }
            // Jika PM 'completed' tapi masih ada item yg belum di-PO, izinkan & reset status
            if ($pm->status === 'completed' && !$pm->isFullyOrdered()) {
                $pm->update(['status' => 'partial_ordered']);
            }
        }

        if (!empty($validated['material_request_id'])) {
            $mr = MaterialRequest::findOrFail($validated['material_request_id']);
            if (!in_array($mr->status, ['approved', 'manager_approved'])) {
                throw ValidationException::withMessages(['status' => 'MR harus sudah diapprove sebelum membuat PO']);
            }
        }

        $po = DB::transaction(function () use ($validated, $request, $pmIds) {
            // Hitung subtotal dari semua item (harga × qty)
            $subtotal = 0;
            $items = [];
            foreach ($validated['items'] as $item) {
                $harga = $item['harga_satuan'] ?? 0;
                $gross = $harga * $item['qty'];
                $subtotal += $gross;
                $items[] = array_merge($item, [
                    'harga_satuan'  => $harga,
                    'diskon_persen' => 0,
                    'diskon_amount' => 0,
                    'total_harga'   => $gross,
                ]);
            }

            // Diskon global di level PO
            $diskonPct    = $validated['diskon_persen'] ?? 0;
            $diskonAmount = round($subtotal * $diskonPct / 100, 2);
            $totalAmount  = $subtotal - $diskonAmount;

            // PPN dihitung dari subtotal setelah diskon
            $ppnPercent = $validated['ppn_percent'] ?? 0;
            $ppnAmount  = round($totalAmount * $ppnPercent / 100, 2);
            $grandTotal = $totalAmount + $ppnAmount;

            // Ambil PM pertama sebagai nilai kolom lama (backward compat)
            $firstPmId = $pmIds->first();

            $po = PurchaseOrder::create([
                'po_number'                => PurchaseOrder::generateNumber(),
                'material_request_id'      => $validated['material_request_id'] ?? null,
                'permintaan_material_id'   => $firstPmId,
                'warehouse_id'             => $validated['warehouse_id'],
                'created_by'               => $request->user()->id,
                'status'                   => 'draft',
                'vendor_name'              => $validated['vendor_name'],
                'vendor_contact'           => $validated['vendor_contact'] ?? null,
                'total_amount'             => $totalAmount,
                'diskon_persen'            => $diskonPct,
                'diskon_amount'            => $diskonAmount,
                'ppn_percent'              => $ppnPercent,
                'ppn_amount'               => $ppnAmount,
                'grand_total'              => $grandTotal,
                'expected_date'            => $validated['expected_date'] ?? null,
                'notes'                    => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create(array_merge($item, ['purchase_order_id' => $po->id]));
            }

            // Sync relasi many-to-many
            if ($pmIds->isNotEmpty()) {
                $po->permintaanMaterials()->sync($pmIds->toArray());
            }

            // Update status setiap PM berdasarkan kondisi
            foreach ($pmIds as $pmId) {
                $pm = PermintaanMaterial::with('items')->find($pmId);
                if ($pm) {
                    $newStatus = $pm->isFullyOrdered() ? 'purchasing' : 'partial_ordered';
                    $pm->update(['status' => $newStatus]);
                }
            }

            // Update MR jika ada
            if (!empty($validated['material_request_id'])) {
                MaterialRequest::find($validated['material_request_id'])->update(['status' => 'purchasing']);
            }

            return $po->load('items', 'warehouse', 'creator', 'materialRequest', 'permintaanMaterials');
        });

        return response()->json(['success' => true, 'data' => $po, 'message' => 'Purchase Order berhasil dibuat'], 201);
    }

    // GET /purchase-orders/{id}
    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json([
            'success' => true,
            'data'    => $purchaseOrder->load(
                'items.item',
                'items.permintaanMaterialItem',
                'warehouse',
                'creator',
                'materialRequest',
                'permintaanMaterials.items',
                'suratJalan'
            ),
        ]);
    }

    // POST /purchase-orders/{id}/send
    public function sendToVendor(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'PO sudah dikirim sebelumnya']);
        }
        $purchaseOrder->update(['status' => 'sent_to_vendor']);
        return response()->json(['success' => true, 'data' => $purchaseOrder, 'message' => 'PO dikirim ke vendor']);
    }
}