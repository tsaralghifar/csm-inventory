<?php

namespace App\Services;

use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Ambil daftar Purchase Order dengan filter & paginasi.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['materialRequest', 'permintaanMaterials', 'warehouse', 'creator'])
            ->withCount(['items', 'suratJalan'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $statuses = array_filter(array_map('trim', explode(',', $request->status)));
            count($statuses) > 1
                ? $query->whereIn('status', $statuses)
                : $query->where('status', $statuses[0]);
        }

        if ($request->exclude_delivery_completed) {
            $query->where(function ($q) {
                $q->whereNull('delivery_status')
                  ->orWhere('delivery_status', '!=', 'completed');
            })->whereNotIn('status', ['draft', 'cancelled']);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'ilike', "%{$search}%")
                  ->orWhere('vendor_name', 'ilike', "%{$search}%");
            });
        }

        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Buat Purchase Order baru beserta item-itemnya.
     */
    public function create(array $validated, int $userId): PurchaseOrder
    {
        // Normalisasi PM IDs
        $pmIds = $this->normalizePmIds($validated);

        $this->assertSourceExists($validated, $pmIds);
        $this->validatePmStatuses($pmIds);
        $this->validateMrStatus($validated);

        return DB::transaction(function () use ($validated, $userId, $pmIds) {
            [$subtotal, $items] = $this->calculateItems($validated['items']);

            [$diskonAmount, $totalAmount, $ppnAmount, $grandTotal] = $this->calculateTotals(
                $subtotal,
                $validated['diskon_persen'] ?? 0,
                $validated['ppn_percent'] ?? 0
            );

            $po = PurchaseOrder::create([
                'po_number'              => PurchaseOrder::generateNumber(),
                'material_request_id'    => $validated['material_request_id'] ?? null,
                'permintaan_material_id' => $pmIds->first(),
                'warehouse_id'           => $validated['warehouse_id'],
                'created_by'             => $userId,
                'status'                 => 'draft',
                'vendor_name'            => $validated['vendor_name'],
                'vendor_contact'         => $validated['vendor_contact'] ?? null,
                'total_amount'           => $totalAmount,
                'diskon_persen'          => $validated['diskon_persen'] ?? 0,
                'diskon_amount'          => $diskonAmount,
                'ppn_percent'            => $validated['ppn_percent'] ?? 0,
                'ppn_amount'             => $ppnAmount,
                'grand_total'            => $grandTotal,
                'expected_date'          => $validated['expected_date'] ?? null,
                'notes'                  => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create(array_merge($item, ['purchase_order_id' => $po->id]));
            }

            if ($pmIds->isNotEmpty()) {
                $po->permintaanMaterials()->sync($pmIds->toArray());
            }

            $this->updatePmStatuses($pmIds);
            $this->updateMrStatus($validated);

            return $po->load('items', 'warehouse', 'creator', 'materialRequest', 'permintaanMaterials');
        });
    }

    /**
     * Kirim PO ke vendor (ubah status dari draft → sent_to_vendor).
     */
    public function sendToVendor(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'PO sudah dikirim sebelumnya']);
        }

        $purchaseOrder->update(['status' => 'sent_to_vendor']);

        return $purchaseOrder->fresh();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function normalizePmIds(array $validated): Collection
    {
        $pmIds = collect($validated['permintaan_material_ids'] ?? []);

        if (!empty($validated['permintaan_material_id'])) {
            $pmIds->push($validated['permintaan_material_id']);
        }

        return $pmIds->unique()->values();
    }

    private function assertSourceExists(array $validated, Collection $pmIds): void
    {
        if (empty($validated['material_request_id']) && $pmIds->isEmpty()) {
            throw ValidationException::withMessages([
                'source' => 'Harus ada setidaknya satu Permintaan Material atau Material Request.',
            ]);
        }
    }

    private function validatePmStatuses(Collection $pmIds): void
    {
        $allowedStatuses = ['approved', 'manager_approved', 'pending_purchasing', 'purchasing', 'partial_ordered', 'completed'];

        foreach ($pmIds as $pmId) {
            $pm = PermintaanMaterial::findOrFail($pmId);

            if (!in_array($pm->status, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'status' => "PM {$pm->nomor} harus sudah disetujui sebelum membuat PO.",
                ]);
            }

            // PM completed tapi ada item belum di-PO → reset ke partial_ordered
            if ($pm->status === 'completed' && !$pm->isFullyOrdered()) {
                $pm->update(['status' => 'partial_ordered']);
            }
        }
    }

    private function validateMrStatus(array $validated): void
    {
        if (empty($validated['material_request_id'])) return;

        $mr = MaterialRequest::findOrFail($validated['material_request_id']);

        if (!in_array($mr->status, ['approved', 'manager_approved'])) {
            throw ValidationException::withMessages([
                'status' => 'MR harus sudah diapprove sebelum membuat PO',
            ]);
        }
    }

    private function calculateItems(array $rawItems): array
    {
        $subtotal = 0;
        $items    = [];

        foreach ($rawItems as $item) {
            $harga        = $item['harga_satuan'] ?? 0;
            $itemDiskon   = $item['diskon_persen'] ?? 0;
            $gross        = $harga * $item['qty'];
            $itemDiskonAmt = round($gross * $itemDiskon / 100, 2);
            $net          = $gross - $itemDiskonAmt;
            $subtotal    += $net;

            $items[] = array_merge($item, [
                'harga_satuan'  => $harga,
                'diskon_persen' => $itemDiskon,
                'diskon_amount' => $itemDiskonAmt,
                'total_harga'   => $net,
            ]);
        }

        return [$subtotal, $items];
    }

    private function calculateTotals(float $subtotal, float $diskonPct, float $ppnPercent): array
    {
        $diskonAmount = round($subtotal * $diskonPct / 100, 2);
        $totalAmount  = $subtotal - $diskonAmount;
        $ppnAmount    = round($totalAmount * $ppnPercent / 100, 2);
        $grandTotal   = $totalAmount + $ppnAmount;

        return [$diskonAmount, $totalAmount, $ppnAmount, $grandTotal];
    }

    private function updatePmStatuses(Collection $pmIds): void
    {
        foreach ($pmIds as $pmId) {
            $pm = PermintaanMaterial::with('items')->find($pmId);
            if ($pm) {
                $newStatus = $pm->isFullyOrdered() ? 'purchasing' : 'partial_ordered';
                $pm->update(['status' => $newStatus]);
            }
        }
    }

    private function updateMrStatus(array $validated): void
    {
        if (!empty($validated['material_request_id'])) {
            MaterialRequest::find($validated['material_request_id'])
                ?->update(['status' => 'purchasing']);
        }
    }
}
