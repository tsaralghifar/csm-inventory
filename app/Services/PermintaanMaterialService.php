<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PermintaanMaterial;
use App\Models\PermintaanMaterialItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class PermintaanMaterialService
{
    /**
     * Ambil daftar Permintaan Material dengan filter & role-based visibility.
     */
    public function list(Request $request, $user): LengthAwarePaginator
    {
        $query = PermintaanMaterial::with(['warehouse', 'requester', 'chiefAuthorizer', 'managerApprover', 'hoApprover'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        // Filter berdasarkan role
        // Role dengan akses semua gudang: superuser, admin_ho, logistik_ho, manager, chief_mekanik, purchasing
        $canSeeAll = $user->isSuperuser()
            || $user->isAdminHO()
            || $user->isLogistikHO()
            || $user->hasRole('manager')
            || $user->hasRole('chief_mekanik')
            || $user->hasRole('purchasing');

        if (!$canSeeAll) {
            // logistik_site dan role lainnya: hanya lihat PM dari gudang sendiri
            $query->where('warehouse_id', $user->warehouse_id);
        }

        if ($request->status) {
            $statuses = array_filter(explode(',', $request->status));
            count($statuses) > 1
                ? $query->whereIn('status', $statuses)
                : $query->where('status', $request->status);
        }

        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->type)         $query->where('type', $request->type);
        if ($request->date_from)    $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)      $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->search)       $query->where('nomor', 'ilike', "%{$request->search}%");

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Buat Permintaan Material baru beserta item-nya.
     * Item baru (is_new_item) otomatis didaftarkan ke master barang.
     *
     * @return array{pm: PermintaanMaterial, new_items_count: int}
     */
    public function store(array $validated, int $userId): array
    {
        // Resolve item baru yang ternyata sudah ada di master (part_number sama)
        foreach ($validated['items'] as $idx => $itemData) {
            if (!empty($itemData['is_new_item'])) {
                // Guard defensif: pastikan field wajib ada meski validasi controller di-bypass
                if (empty($itemData['new_part_number'])) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.new_part_number" => 'Part Number wajib diisi untuk barang baru.',
                    ]);
                }
                if (empty($itemData['new_category_id'])) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.new_category_id" => 'Kategori wajib dipilih untuk barang baru.',
                    ]);
                }

                $existing = Item::where('part_number', $itemData['new_part_number'])->first();
                if ($existing) {
                    $validated['items'][$idx]['is_new_item']  = false;
                    $validated['items'][$idx]['item_id']      = $existing->id;
                    $validated['items'][$idx]['part_number']  = $existing->part_number;
                    $validated['items'][$idx]['nama_barang']  = $validated['items'][$idx]['nama_barang'] ?: $existing->name;
                    $validated['items'][$idx]['satuan']       = $validated['items'][$idx]['satuan'] ?: $existing->unit;
                }
            }
        }

        $newItemsCount = 0;

        $pm = DB::transaction(function () use ($validated, $userId, &$newItemsCount) {
            $pm = PermintaanMaterial::create([
                'nomor'        => PermintaanMaterial::generateNomor(),
                'warehouse_id' => $validated['warehouse_id'],
                'type'         => $validated['type'] ?? 'part',
                'requested_by' => $userId,
                'status'       => 'draft',
                'notes'        => $validated['notes'] ?? null,
                'needed_date'  => $validated['needed_date'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $resolvedItemId = $itemData['item_id'] ?? null;

                if (!empty($itemData['is_new_item'])) {
                    // Guard: new_part_number wajib ada
                    if (empty($itemData['new_part_number'])) {
                        throw ValidationException::withMessages([
                            "items.{$idx}.new_part_number" => "Part Number wajib diisi untuk barang baru: \"{$itemData['nama_barang']}\"",
                        ]);
                    }
                    $newItem = Item::firstOrCreate(
                        ['part_number' => $itemData['new_part_number']],
                        [
                            'name'        => $itemData['nama_barang'],
                            'category_id' => $itemData['new_category_id'],
                            'brand'       => $itemData['new_brand'] ?? null,
                            'unit'        => $itemData['satuan'],
                            'min_stock'   => $itemData['new_min_stock'] ?? 0,
                            'price'       => 0,
                            'is_active'   => true,
                        ]
                    );

                    ItemStock::firstOrCreate(
                        ['item_id' => $newItem->id, 'warehouse_id' => $validated['warehouse_id']],
                        ['qty' => 0, 'qty_reserved' => 0]
                    );

                    $resolvedItemId = $newItem->id;
                    $newItemsCount++;
                }

                PermintaanMaterialItem::create([
                    'permintaan_material_id' => $pm->id,
                    'item_id'                => $resolvedItemId,
                    'part_number'            => !empty($itemData['is_new_item'])
                                                    ? ($itemData['new_part_number'] ?? null)
                                                    : ($itemData['part_number'] ?? null),
                    'nama_barang'            => $itemData['nama_barang'],
                    'kode_unit'              => $itemData['kode_unit'] ?? null,
                    'tipe_unit'              => $itemData['tipe_unit'] ?? null,
                    'qty'                    => $itemData['qty'],
                    'satuan'                 => $itemData['satuan'],
                    'keterangan'             => $itemData['keterangan'] ?? null,
                ]);
            }

            return $pm->load('items', 'warehouse', 'requester');
        });

        return ['pm' => $pm, 'new_items_count' => $newItemsCount];
    }

    /**
     * Submit draft PM ke alur approval berikutnya.
     */
    public function submit(PermintaanMaterial $pm): array
    {
        if ($pm->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya permintaan berstatus draft yang bisa disubmit',
            ]);
        }

        if ($pm->type === 'part') {
            $pm->update(['status' => 'pending_chief']);
            $message = 'Permintaan berhasil disubmit ke Chief Mekanik';
        } else {
            $pm->update(['status' => 'pending_ho']);
            $message = 'Permintaan berhasil disubmit ke Admin HO';
        }

        return ['pm' => $pm->fresh('warehouse', 'requester'), 'message' => $message];
    }

    /**
     * Otorisasi oleh Chief Mekanik.
     */
    public function authorizeChief(PermintaanMaterial $pm, int $userId): PermintaanMaterial
    {
        if ($pm->status !== 'pending_chief') {
            throw ValidationException::withMessages([
                'status' => 'Status permintaan tidak valid untuk diotorisasi Chief Mekanik',
            ]);
        }

        $pm->update([
            'status'              => 'pending_manager',
            'chief_authorized_by' => $userId,
            'chief_authorized_at' => now(),
        ]);

        return $pm->fresh();
    }

    /**
     * Approval oleh Manager.
     */
    public function approveManager(PermintaanMaterial $pm, int $userId): PermintaanMaterial
    {
        if ($pm->status !== 'pending_manager') {
            throw ValidationException::withMessages([
                'status' => 'Status permintaan tidak valid untuk disetujui Manager',
            ]);
        }

        $pm->update([
            'status'              => 'pending_ho',
            'manager_approved_by' => $userId,
            'manager_approved_at' => now(),
        ]);

        return $pm->fresh();
    }

    /**
     * Approval oleh Admin HO.
     */
    public function approveHO(PermintaanMaterial $pm, int $userId): PermintaanMaterial
    {
        if ($pm->status !== 'pending_ho') {
            throw ValidationException::withMessages([
                'status' => 'Status permintaan tidak valid untuk di-approve Admin HO',
            ]);
        }

        $pm->update([
            'status'         => 'approved',
            'ho_approved_by' => $userId,
            'ho_approved_at' => now(),
        ]);

        return $pm->fresh();
    }

    /**
     * Admin HO mengajukan PM ke Purchasing (buat PO).
     */
    public function submitPurchasing(PermintaanMaterial $pm, int $userId): PermintaanMaterial
    {
        if ($pm->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Hanya PM berstatus "Disetujui HO" yang dapat diajukan ke Purchasing',
            ]);
        }

        $pm->update([
            'status'          => 'pending_purchasing',
            'po_submitted_by' => $userId,
            'po_submitted_at' => now(),
        ]);

        return $pm->fresh('warehouse', 'requester', 'hoApprover', 'poSubmitter');
    }

    /**
     * Tolak Permintaan Material.
     */
    public function reject(PermintaanMaterial $pm, string $reason): PermintaanMaterial
    {
        $rejectableStatuses = ['pending_chief', 'pending_manager', 'pending_ho', 'pending_purchasing'];

        if (!in_array($pm->status, $rejectableStatuses)) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan tidak bisa ditolak pada status ini',
            ]);
        }

        $pm->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $pm->fresh();
    }

    /**
     * Hapus PM (hanya boleh saat status draft).
     */
    public function destroy(PermintaanMaterial $pm): void
    {
        if ($pm->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya permintaan berstatus draft yang bisa dihapus',
            ]);
        }

        $pm->delete();
    }
}