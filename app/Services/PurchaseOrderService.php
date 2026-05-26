<?php

namespace App\Services;

use App\Models\Item;
use App\Models\MaterialRequest;
use App\Models\PartNumberChangeLog;
use App\Models\PermintaanMaterial;
use App\Models\PermintaanMaterialItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    // ─── Query / List ─────────────────────────────────────────────────────────

    public function list(Request $request): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['materialRequest', 'permintaanMaterials', 'warehouse', 'creator', 'supplier'])
            ->withCount(['items', 'suratJalan'])
            ->orderByDesc('created_at');

        $this->applyFilters($query, $request);

        return $query->paginate($request->integer('per_page', 15));
    }

    public function summary(): array
    {
        return [
            'cash_count'     => PurchaseOrder::cash()->whereNotIn('status', [PurchaseOrder::STATUS_CANCELLED])->count(),
            'kredit_count'   => PurchaseOrder::kredit()->whereNotIn('status', [PurchaseOrder::STATUS_CANCELLED])->count(),
            'overdue_count'  => PurchaseOrder::overdue()->count(),
            'near_due_count' => PurchaseOrder::nearDue(7)->count(),
        ];
    }

    // ─── Mutations ────────────────────────────────────────────────────────────

    public function create(array $validated, int $userId): PurchaseOrder
    {
        $pmIds = $this->normalizePmIds($validated);

        $this->guardSource($validated, $pmIds);
        $this->guardPmStatuses($pmIds);
        $this->guardMrStatus($validated);
        $this->guardPaymentType($validated);

        return DB::transaction(function () use ($validated, $userId, $pmIds) {
            $po = $this->buildPO($validated, $userId, $pmIds);

            $this->attachItems($po, $validated['items']);
            $this->syncPermintaanMaterials($po, $pmIds);
            $this->updatePmStatuses($pmIds);
            $this->updateMrStatus($validated);

            return $po->load('items', 'warehouse', 'creator', 'materialRequest', 'permintaanMaterials', 'supplier');
        });
    }

    public function sendToVendor(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'PO sudah dikirim sebelumnya.']);
        }

        $po->update(['status' => PurchaseOrder::STATUS_SENT_TO_VENDOR]);

        return $po->fresh();
    }

    public function markComplete(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po->update([
                'status'          => PurchaseOrder::STATUS_COMPLETED,
                'delivery_status' => PurchaseOrder::STATUS_COMPLETED,
            ]);

            if ($po->isKredit()) {
                $this->createSupplierInvoiceIfNeeded($po);
            }

            return $po->fresh();
        });
    }

    /**
     * Koreksi part number sebuah item di PO.
     *
     * Aturan:
     *  - Status draft        → bebas, notes opsional.
     *  - Status >= sent_to_vendor → field notes WAJIB diisi.
     *  - update_master = true → part_number di tabel items ikut diperbarui.
     */
    public function updatePartNumber(
        PurchaseOrder     $po,
        PurchaseOrderItem $poItem,
        array             $data,
        int               $userId,
    ): PurchaseOrderItem {
        // Notes wajib jika PO sudah dikirim ke vendor
        $requiresNotes = $po->status !== PurchaseOrder::STATUS_DRAFT;

        if ($requiresNotes && empty($data['notes'])) {
            throw ValidationException::withMessages([
                'notes' => 'Catatan alasan perubahan wajib diisi karena PO sudah dikirim ke vendor.',
            ]);
        }

        $newPartNumber = trim($data['new_part_number']);
        $updateMaster  = (bool) ($data['update_master'] ?? false);
        $oldPartNumber = $poItem->part_number;

        // Tidak ada perubahan — bail out lebih awal
        if ($oldPartNumber === $newPartNumber) {
            return $poItem;
        }

        return DB::transaction(function () use (
            $po, $poItem, $newPartNumber, $updateMaster,
            $oldPartNumber, $data, $userId
        ) {
            // 1. Update purchase_order_items
            $poItem->update(['part_number' => $newPartNumber]);

            // 2. Update permintaan_material_items yang terkait
            $pmItemId = $poItem->permintaan_material_item_id;

            if ($pmItemId) {
                PermintaanMaterialItem::where('id', $pmItemId)
                    ->update(['part_number' => $newPartNumber]);
            }

            // 3. Update master barang (opsional berdasarkan flag)
            if ($updateMaster && $poItem->item_id) {
                Item::where('id', $poItem->item_id)
                    ->update(['part_number' => $newPartNumber]);
            }

            // 4. Catat audit log
            PartNumberChangeLog::create([
                'purchase_order_item_id'      => $poItem->id,
                'purchase_order_id'           => $po->id,
                'permintaan_material_item_id' => $pmItemId,
                'item_id'                     => $updateMaster ? $poItem->item_id : null,
                'old_part_number'             => $oldPartNumber,
                'new_part_number'             => $newPartNumber,
                'po_status_at_change'         => $po->status,
                'update_master'               => $updateMaster,
                'notes'                       => $data['notes'] ?? null,
                'changed_by'                  => $userId,
            ]);

            return $poItem->fresh();
        });
    }

    /**
     * Idempotent — aman dipanggil berkali-kali, tidak akan membuat duplikat.
     */
    public function createSupplierInvoiceIfNeeded(PurchaseOrder $po): ?SupplierInvoice
    {
        if (! $po->isKredit()) {
            return null;
        }

        // Cek semua status — jika sudah ada invoice apapun untuk PO ini, return yang unpaid/partial
        $existing = SupplierInvoice::where('purchase_order_id', $po->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Jika sudah ada invoice paid pun, buat yang baru (PO bisa cicil)
        // Tapi kalau ada invoice unpaid/partial, sudah return di atas

        // Jika supplier_id kosong, cari/buat dari vendor_name
        $supplierId = $po->supplier_id;
        if (! $supplierId && $po->vendor_name) {
            $supplier = \App\Models\Supplier::firstOrCreate(
                ['name' => $po->vendor_name],
                ['code' => 'AUTO-' . strtoupper(substr(preg_replace('/\s+/', '', $po->vendor_name), 0, 8)), 'contact_name' => $po->vendor_contact ?? '']
            );
            $supplierId = $supplier->id;
            $po->update(['supplier_id' => $supplierId]);
        }

        if (! $supplierId) {
            throw new \Exception("PO {$po->po_number} tidak memiliki supplier.");
        }

        // internal_number unik per PO — tambah suffix jika sudah ada
        $baseInternal = 'AUTO-' . $po->po_number;
        $internalNumber = $baseInternal;
        $suffix = 2;
        while (SupplierInvoice::where('internal_number', $internalNumber)->exists()) {
            $internalNumber = $baseInternal . '-' . $suffix++;
        }

        return SupplierInvoice::create([
            // invoice_number diisi NULL — supplier belum memberikan nomor invoice resmi.
            // Accounting harus mengisi nomor ini setelah menerima invoice fisik dari supplier.
            'invoice_number'    => null,
            'internal_number'   => $internalNumber,
            'supplier_id'       => $supplierId,
            'purchase_order_id' => $po->id,
            'subtotal'          => $po->total_amount,
            'tax_amount'        => $po->ppn_amount,
            'total_amount'      => $po->grand_total,
            'paid_amount'       => 0,
            'remaining_amount'  => $po->grand_total,
            'invoice_date'      => now()->toDateString(),
            'due_date'          => $po->payment_due_date ?? now()->addDays(30)->toDateString(),
            'status'            => 'unpaid',
            'created_by'        => $po->created_by,
            'notes'             => "Auto-dibuat dari PO {$po->po_number} (kredit {$po->payment_term_days} hari)",
        ]);
    }

    // ─── Private: filters ─────────────────────────────────────────────────────

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $statuses = array_filter(array_map('trim', explode(',', $request->status)));
            count($statuses) > 1
                ? $query->whereIn('status', $statuses)
                : $query->where('status', $statuses[0]);
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        if ($request->boolean('near_due')) {
            $query->nearDue($request->integer('near_due_days', 7));
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        if ($request->boolean('exclude_delivery_completed')) {
            $query->where(fn($q) => $q->whereNull('delivery_status')->orWhere('delivery_status', '!=', 'completed'))
                  ->whereNotIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_CANCELLED]);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($sub) => $sub->where('po_number', 'ilike', "%{$q}%")
                                         ->orWhere('vendor_name', 'ilike', "%{$q}%"));
        }

        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);
    }

    // ─── Private: builders ────────────────────────────────────────────────────

    private function buildPO(array $validated, int $userId, Collection $pmIds): PurchaseOrder
    {
        [$subtotal, $items] = $this->calculateItems($validated['items']);

        [$diskonAmount, $totalAmount, $ppnAmount, $grandTotal] = $this->calculateTotals(
            $subtotal,
            (float) ($validated['diskon_persen'] ?? 0),
            (float) ($validated['ppn_percent']   ?? 0),
        );

        return PurchaseOrder::create([
            'po_number'              => PurchaseOrder::generateNumber(),
            'material_request_id'    => $validated['material_request_id'] ?? null,
            'permintaan_material_id' => $pmIds->first(),
            'warehouse_id'           => $validated['warehouse_id'],
            'supplier_id'            => $validated['supplier_id'] ?? null,
            'created_by'             => $userId,
            'status'                 => PurchaseOrder::STATUS_DRAFT,
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
            'payment_type'           => $validated['payment_type'] ?? PurchaseOrder::PAYMENT_CASH,
            'payment_term_days'      => $validated['payment_term_days'] ?? null,
            'payment_due_date'       => PurchaseOrder::calculateDueDate(
                                            $validated['payment_type'] ?? null,
                                            $validated['payment_term_days'] ?? null,
                                        ),
        ]);
    }

    private function attachItems(PurchaseOrder $po, array $rawItems): void
    {
        [, $items] = $this->calculateItems($rawItems);

        foreach ($items as $item) {
            PurchaseOrderItem::create(array_merge($item, ['purchase_order_id' => $po->id]));
        }
    }

    private function syncPermintaanMaterials(PurchaseOrder $po, Collection $pmIds): void
    {
        if ($pmIds->isNotEmpty()) {
            $po->permintaanMaterials()->sync($pmIds->toArray());
        }
    }

    // ─── Private: guards ──────────────────────────────────────────────────────

    private function guardSource(array $validated, Collection $pmIds): void
    {
        if (empty($validated['material_request_id']) && $pmIds->isEmpty()) {
            throw ValidationException::withMessages([
                'source' => 'Harus ada setidaknya satu Permintaan Material atau Material Request.',
            ]);
        }
    }

    private function guardPmStatuses(Collection $pmIds): void
    {
        $allowed = ['approved', 'manager_approved', 'pending_purchasing', 'purchasing', 'partial_ordered', 'completed'];

        foreach ($pmIds as $pmId) {
            $pm = PermintaanMaterial::findOrFail($pmId);

            if (! in_array($pm->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "PM {$pm->nomor} harus sudah disetujui sebelum membuat PO.",
                ]);
            }

            if ($pm->status === 'completed' && ! $pm->isFullyOrdered()) {
                $pm->update(['status' => 'partial_ordered']);
            }
        }
    }

    private function guardMrStatus(array $validated): void
    {
        if (empty($validated['material_request_id'])) return;

        $mr = MaterialRequest::findOrFail($validated['material_request_id']);

        if (! in_array($mr->status, ['approved', 'manager_approved'], true)) {
            throw ValidationException::withMessages([
                'status' => 'MR harus sudah diapprove sebelum membuat PO.',
            ]);
        }
    }

    private function guardPaymentType(array $validated): void
    {
        if (($validated['payment_type'] ?? PurchaseOrder::PAYMENT_CASH) === PurchaseOrder::PAYMENT_KREDIT) {
            if (empty($validated['payment_term_days']) || (int) $validated['payment_term_days'] < 1) {
                throw ValidationException::withMessages([
                    'payment_term_days' => 'Tenor wajib diisi dan minimal 1 hari untuk PO kredit.',
                ]);
            }
        }
    }

    // ─── Private: calculations ────────────────────────────────────────────────

    private function calculateItems(array $rawItems): array
    {
        $subtotal = 0.0;
        $items    = [];

        foreach ($rawItems as $item) {
            $harga      = (float) ($item['harga_satuan'] ?? 0);
            $diskonPct  = (float) ($item['diskon_persen'] ?? 0);
            $gross      = $harga * (float) $item['qty'];
            $diskonAmt  = round($gross * $diskonPct / 100, 2);
            $net        = $gross - $diskonAmt;
            $subtotal  += $net;

            $items[] = array_merge($item, [
                'harga_satuan'  => $harga,
                'diskon_persen' => $diskonPct,
                'diskon_amount' => $diskonAmt,
                'total_harga'   => $net,
            ]);
        }

        return [$subtotal, $items];
    }

    private function calculateTotals(float $subtotal, float $diskonPct, float $ppnPct): array
    {
        $diskon     = round($subtotal * $diskonPct / 100, 2);
        $afterDisk  = $subtotal - $diskon;
        $ppn        = round($afterDisk * $ppnPct / 100, 2);

        return [$diskon, $afterDisk, $ppn, $afterDisk + $ppn];
    }

    // ─── Private: status updaters ─────────────────────────────────────────────

    private function updatePmStatuses(Collection $pmIds): void
    {
        PermintaanMaterial::with('items')
            ->whereIn('id', $pmIds)
            ->get()
            ->each(fn($pm) => $pm->update([
                'status' => $pm->isFullyOrdered() ? 'purchasing' : 'partial_ordered',
            ]));
    }

    private function updateMrStatus(array $validated): void
    {
        if (! empty($validated['material_request_id'])) {
            MaterialRequest::find($validated['material_request_id'])
                ?->update(['status' => 'purchasing']);
        }
    }

    // ─── Private: generators ──────────────────────────────────────────────────

    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';

        $last = SupplierInvoice::where('invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('invoice_number');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return sprintf('%s%04d', $prefix, $seq);
    }

    // ─── Private: normalizers ─────────────────────────────────────────────────

    private function normalizePmIds(array $validated): Collection
    {
        return collect($validated['permintaan_material_ids'] ?? [])
            ->when(
                ! empty($validated['permintaan_material_id']),
                fn($c) => $c->push($validated['permintaan_material_id'])
            )
            ->unique()
            ->values();
    }
}