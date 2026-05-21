<?php

namespace App\Services;

use App\Models\BonPengeluaran;
use App\Models\BonPengeluaranItem;
use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\StockLayer;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\BonPengeluaranConfirmationNotification;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class BonPengeluaranService
{
    /**
     * Ambil daftar Bon Pengeluaran dengan filter.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = BonPengeluaran::select([
                'id', 'bon_number', 'status', 'warehouse_id', 'created_by', 'approved_by',
                'material_request_id', 'permintaan_material_id',
                'received_by', 'mechanic', 'issue_date', 'created_at',
            ])
            ->with([
                // Hanya ambil kolom yang ditampilkan di tabel list
                'materialRequest:id,mr_number',
                'permintaanMaterial:id,nomor',
                'warehouse:id,name',
                'creator:id,name',
            ])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->status)    $query->where('status', $request->status);
        if ($request->search)    $query->where('bon_number', 'ilike', '%' . $request->search . '%');
        if ($request->date_from) $query->whereDate('issue_date', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('issue_date', '<=', $request->date_to);

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Buat Bon Pengeluaran baru.
     * Bisa dari MR, PM, atau manual (StokHO/StokSite).
     * Jika auto_issue = true → langsung issue stok (bypass konfirmasi).
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
     * Admin siapkan barang → ubah status ke pending_confirmation
     * dan kirim notifikasi ke mekanik untuk konfirmasi kesesuaian.
     */
    public function requestConfirmation(BonPengeluaran $bon, int $userId): BonPengeluaran
    {
        if ($bon->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya Bon Pengeluaran draft yang bisa dimintakan konfirmasi.',
            ]);
        }

        $bon->load('items.item', 'warehouse');

        DB::transaction(function () use ($bon, $userId) {
            $bon->update([
                'status'             => 'pending_confirmation',
                'confirmation_token' => Str::random(32),
            ]);
        });

        // Kirim notifikasi ke mekanik yang bersangkutan
        $this->notifyMechanic($bon);

        return $bon->fresh()->load('items.item', 'warehouse', 'creator');
    }

    /**
     * Mekanik konfirmasi barang cocok → status confirmed, siap diissue admin.
     */
    public function confirmByMechanic(BonPengeluaran $bon, string $confirmedBy): BonPengeluaran
    {
        if ($bon->status !== 'pending_confirmation') {
            throw ValidationException::withMessages([
                'status' => 'Bon ini tidak dalam status menunggu konfirmasi.',
            ]);
        }

        $bon->update([
            'status'       => 'confirmed',
            'confirmed_by' => $confirmedBy,
            'confirmed_at' => now(),
        ]);

        return $bon->fresh()->load('items.item', 'warehouse');
    }

    /**
     * Mekanik menolak barang (tidak sesuai) → status kembali ke draft dengan catatan.
     */
    public function rejectByMechanic(BonPengeluaran $bon, string $reason, string $rejectedBy): BonPengeluaran
    {
        if ($bon->status !== 'pending_confirmation') {
            throw ValidationException::withMessages([
                'status' => 'Bon ini tidak dalam status menunggu konfirmasi.',
            ]);
        }

        $bon->update([
            'status'           => 'rejected_by_mechanic',
            'rejection_reason' => $reason,
            'confirmed_by'     => $rejectedBy,
            'confirmed_at'     => now(),
        ]);

        // Notif balik ke admin gudang bahwa barang ditolak mekanik
        $this->notifyAdminOnRejection($bon);

        return $bon->fresh()->load('items.item', 'warehouse');
    }

    /**
     * Admin revisi bon yang ditolak mekanik → kembali ke draft.
     */
    public function revise(BonPengeluaran $bon): BonPengeluaran
    {
        if ($bon->status !== 'rejected_by_mechanic') {
            throw ValidationException::withMessages([
                'status' => 'Hanya bon yang ditolak mekanik yang bisa direvisi.',
            ]);
        }

        $bon->update([
            'status'           => 'draft',
            'rejection_reason' => null,
            'confirmed_by'     => null,
            'confirmed_at'     => null,
            'confirmation_token' => null,
        ]);

        return $bon->fresh();
    }

    /**
     * Admin issue barang → kurangi stok.
     * Hanya bisa dari status confirmed (sudah dikonfirmasi mekanik)
     * atau draft (jika auto_issue / bypass konfirmasi untuk kasus darurat).
     */
    public function approve(BonPengeluaran $bon, int $userId): BonPengeluaran
    {
        $allowedStatuses = ['draft', 'confirmed'];

        if (!in_array($bon->status, $allowedStatuses)) {
            $statusLabel = match ($bon->status) {
                'pending_confirmation'  => 'masih menunggu konfirmasi mekanik',
                'rejected_by_mechanic'  => 'ditolak mekanik — perlu direvisi dulu',
                'issued'                => 'sudah pernah dikeluarkan',
                default                 => 'tidak valid untuk dikeluarkan',
            };
            throw ValidationException::withMessages([
                'status' => 'Bon Pengeluaran tidak bisa dikeluarkan: ' . $statusLabel . '.',
            ]);
        }

        DB::transaction(function () use ($bon, $userId) {
            $this->issueStock($bon, $userId);
        });

        return $bon->fresh()->load('items', 'warehouse', 'approver');
    }

    /**
     * Update items pada Bon Pengeluaran (hanya status draft atau rejected_by_mechanic).
     * Bisa ubah qty, ganti item, hapus item, atau tambah item baru.
     */
    public function updateItems(BonPengeluaran $bon, array $items): BonPengeluaran
    {
        $editableStatuses = ['draft', 'rejected_by_mechanic'];

        if (!in_array($bon->status, $editableStatuses)) {
            throw ValidationException::withMessages([
                'status' => 'Item hanya bisa diedit saat bon berstatus draft atau ditolak mekanik.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Minimal harus ada 1 item.',
            ]);
        }

        DB::transaction(function () use ($bon, $items) {
            // Hapus semua item lama, ganti dengan yang baru
            $bon->items()->delete();

            foreach ($items as $item) {
                BonPengeluaranItem::create([
                    'bon_pengeluaran_id' => $bon->id,
                    'item_id'            => $item['item_id'] ?? null,
                    'nama_barang'        => $item['nama_barang'],
                    'qty'                => $item['qty'],
                    'satuan'             => $item['satuan'],
                    'keterangan'         => $item['keterangan'] ?? null,
                ]);
            }

            // Jika sebelumnya ditolak mekanik, reset ke draft setelah edit
            if ($bon->status === 'rejected_by_mechanic') {
                $bon->update([
                    'status'             => 'draft',
                    'rejection_reason'   => null,
                    'confirmed_by'       => null,
                    'confirmed_at'       => null,
                    'confirmation_token' => null,
                ]);
            }
        });

        return $bon->fresh()->load('items.item', 'warehouse', 'creator');
    }

    /**
     * Hapus Bon Pengeluaran (hanya status draft atau rejected_by_mechanic).
     */
    public function delete(BonPengeluaran $bon): void
    {
        $deletableStatuses = ['draft', 'rejected_by_mechanic'];

        if (!in_array($bon->status, $deletableStatuses)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya Bon Pengeluaran draft atau yang ditolak yang bisa dihapus.',
            ]);
        }

        $bon->delete();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Kirim notifikasi konfirmasi ke mekanik yang bersangkutan.
     * Cari user berdasarkan nama mekanik atau fallback ke admin gudang.
     */
    private function notifyMechanic(BonPengeluaran $bon): void
    {
        // Cari user mekanik berdasarkan nama (jika ada)
        $mechanics = collect();

        if ($bon->mechanic) {
            $mechanics = User::where('name', 'ilike', '%' . $bon->mechanic . '%')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['mekanik', 'mechanic', 'operator']))
                ->get();
        }

        // Jika tidak ketemu, kirim ke semua mekanik di gudang tersebut
        if ($mechanics->isEmpty() && $bon->warehouse_id) {
            $mechanics = User::where('warehouse_id', $bon->warehouse_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['mekanik', 'mechanic', 'operator']))
                ->get();
        }

        // Fallback: kirim ke admin gudang setempat
        if ($mechanics->isEmpty() && $bon->warehouse_id) {
            $mechanics = User::where('warehouse_id', $bon->warehouse_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin_gudang'))
                ->get();
        }

        $bon->load('items.item', 'warehouse');

        foreach ($mechanics as $user) {
            $user->notify(new BonPengeluaranConfirmationNotification($bon));
        }
    }

    /**
     * Notif ke admin gudang bahwa mekanik menolak barang.
     */
    private function notifyAdminOnRejection(BonPengeluaran $bon): void
    {
        if (!$bon->warehouse_id) return;

        $admins = User::where('warehouse_id', $bon->warehouse_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin_gudang', 'superuser', 'admin_ho']))
            ->get();

        $payload = [
            'type'       => 'bon_pengeluaran',
            'action'     => 'rejected_by_mechanic',
            'id'         => $bon->id,
            'bon_number' => $bon->bon_number,
            'status'     => $bon->status,
            'title'      => 'Barang Ditolak Mekanik',
            'message'    => 'Bon ' . $bon->bon_number . ' ditolak oleh mekanik: ' . ($bon->rejection_reason ?? '-')
                            . '. Mohon lakukan revisi.',
            'url'        => '/bon-pengeluaran/' . $bon->id,
        ];

        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\DocumentStatusNotification($payload));
        }
    }

    /**
     * Issue stok dengan metode FIFO.
     * Setiap item akan mengonsumsi layer terlama terlebih dahulu.
     * Harga FIFO aktual dicatat ke BonPengeluaranItem dan StockMovement.
     */
    private function issueStock(BonPengeluaran $bon, int $userId): void
    {
        $bon->load('items');

        $index = 1;
        foreach ($bon->items as $bonItem) {
            if (!$bonItem->item_id) continue;

            $qtyNeeded = (float) $bonItem->qty;

            // ── Validasi stok tersedia ────────────────────────────────────────
            $stock = ItemStock::where('item_id', $bonItem->item_id)
                ->where('warehouse_id', $bon->warehouse_id)
                ->lockForUpdate()
                ->first();

            if (!$stock || (float) $stock->qty < $qtyNeeded) {
                throw ValidationException::withMessages([
                    'stock' => 'Stok tidak cukup untuk item: ' . $bonItem->nama_barang
                               . '. Tersedia: ' . ($stock?->qty ?? 0)
                               . ', Dibutuhkan: ' . $qtyNeeded,
                ]);
            }

            // ── Consume FIFO layers ───────────────────────────────────────────
            $layers = StockLayer::forStock($bonItem->item_id, $bon->warehouse_id)
                ->available()
                ->fifo()
                ->lockForUpdate()
                ->get();

            $qtyRemaining   = $qtyNeeded;
            $totalFifoValue = 0.0;   // akumulasi nilai untuk hitung weighted avg
            $layerDetails   = [];    // untuk catatan di notes movement

            foreach ($layers as $layer) {
                if ($qtyRemaining <= 0) break;

                $ambil = min((float) $layer->qty_sisa, $qtyRemaining);

                // Kurangi qty_sisa layer
                $layer->qty_sisa -= $ambil;
                $layer->save();

                $totalFifoValue += $ambil * (float) $layer->harga_satuan;
                $qtyRemaining   -= $ambil;
                $layerDetails[]  = "Tgl {$layer->tanggal_masuk->format('d/m/Y')}: {$ambil} @ Rp " .
                                   number_format($layer->harga_satuan, 0, ',', '.');
            }

            // Jika layer tidak cukup cover (edge case: layer belum dibuat untuk stok lama)
            // fallback ke avg_price untuk sisa qty
            if ($qtyRemaining > 0) {
                $fallbackHarga   = (float) ($stock->avg_price ?? \App\Models\Item::find($bonItem->item_id)?->price ?? 0);
                $totalFifoValue += $qtyRemaining * $fallbackHarga;
                $layerDetails[]  = "Stok lama (avg): {$qtyRemaining} @ Rp " .
                                   number_format($fallbackHarga, 0, ',', '.');
            }

            // Harga FIFO rata-rata tertimbang untuk seluruh qty yang dikeluarkan
            $fifoPrice = $qtyNeeded > 0 ? round($totalFifoValue / $qtyNeeded, 2) : 0;

            // ── Update ItemStock ──────────────────────────────────────────────
            $qtyBefore = (float) $stock->qty;
            $stock->decrement('qty', $qtyNeeded);

            // ── Simpan harga FIFO ke BonPengeluaranItem ───────────────────────
            $bonItem->update([
                'harga_satuan' => $fifoPrice,
                'fifo_price'   => $fifoPrice,
            ]);

            // ── Buat StockMovement dengan harga FIFO ──────────────────────────
            StockMovement::create([
                'item_id'           => $bonItem->item_id,
                'from_warehouse_id' => $bon->warehouse_id,
                'type'              => 'out',
                'qty'               => $qtyNeeded,
                'qty_before'        => $qtyBefore,
                'qty_after'         => $qtyBefore - $qtyNeeded,
                'price'             => $fifoPrice,
                'fifo_price'        => $fifoPrice,
                'reference_no'      => $bon->bon_number . '-' . str_pad($index++, 3, '0', STR_PAD_LEFT),
                'notes'             => 'Bon Pengeluaran: ' . $bon->bon_number
                                       . ' | FIFO: ' . implode(', ', $layerDetails),
                'unit_code'         => $bon->unit_code,
                'unit_type'         => $bon->unit_type,
                'hm_km'             => $bon->hm_km,
                'mechanic'          => $bon->mechanic,
                'moveable_type'     => BonPengeluaran::class,
                'moveable_id'       => $bon->id,
                'movement_date'     => $bon->issue_date,
                'created_by'        => $userId,
            ]);

            // ── Low stock check ───────────────────────────────────────────────
            app(LowStockAlertService::class)->checkAndAlert($bon->warehouse_id, $bonItem->item_id);
        }

        $bon->update([
            'status'      => 'issued',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

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

            $qtyIssuedByItem = \App\Models\BonPengeluaranItem::whereHas('bonPengeluaran', function ($q) use ($pm) {
                    $q->where('permintaan_material_id', $pm->id)
                      ->where('status', 'issued');
                })
                ->selectRaw('item_id, nama_barang, SUM(qty) as total_issued')
                ->groupBy('item_id', 'nama_barang')
                ->get()
                ->keyBy('item_id');

            $allFulfilled = $pm->items->every(function ($pmItem) use ($qtyIssuedByItem) {
                $issued = isset($qtyIssuedByItem[$pmItem->item_id])
                    ? (float) $qtyIssuedByItem[$pmItem->item_id]->total_issued
                    : 0;
                return $issued >= (float) $pmItem->qty;
            });

            if ($allFulfilled) {
                $pm->update(['status' => 'completed']);
            } elseif ($pm->status !== 'completed') {
                $pm->update(['status' => 'partial_issued']);
            }
        }
    }
}