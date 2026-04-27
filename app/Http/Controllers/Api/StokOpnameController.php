<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokOpnameController extends Controller
{
    public function __construct(private StockService $stockService) {}

    // GET /stok-opname
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = StokOpname::with(['warehouse', 'dibuatOleh', 'disetujuiOleh'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if (!$user->isSuperuser() && !$user->isAdminHO()) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->status)       $query->where('status', $request->status);
        if ($request->date_from)    $query->whereDate('tanggal_opname', '>=', $request->date_from);
        if ($request->date_to)      $query->whereDate('tanggal_opname', '<=', $request->date_to);
        if ($request->search)       $query->where('nomor', 'ilike', "%{$request->search}%");

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /stok-opname
    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'          => 'required|exists:warehouses,id',
            'tipe'                  => 'required|string|max:100',
            'no_referensi'          => 'required|string|max:100',
            'keterangan'            => 'nullable|string',
            'tanggal_opname'        => 'required|date',
            'items'                 => 'required|array|min:1',
            'items.*.item_id'       => 'required|exists:items,id',
            'items.*.qty_fisik'     => 'required|numeric|min:0',
            'items.*.keterangan'    => 'nullable|string',
        ]);

        $nomor = DB::transaction(function () use ($request) {
            // Generate nomor ADJ-YYYYMMDD-XXXX
            $dateStr = now()->format('Ymd');
            $prefix  = "ADJ-{$dateStr}-";
            $last    = StokOpname::where('nomor', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByRaw("CAST(SUBSTRING(nomor FROM " . (strlen($prefix) + 1) . ") AS INTEGER) DESC")
                ->value('nomor');
            $next = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
            $nomor = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);

            $opname = StokOpname::create([
                'nomor'          => $nomor,
                'warehouse_id'   => $request->warehouse_id,
                'tipe'           => $request->tipe,
                'no_referensi'   => $request->no_referensi,
                'keterangan'     => $request->keterangan,
                'tanggal_opname' => $request->tanggal_opname,
                'status'         => 'draft',
                'dibuat_oleh'    => $request->user()->id,
            ]);

            foreach ($request->items as $row) {
                // Ambil qty sistem saat ini
                $stock = ItemStock::where('item_id', $row['item_id'])
                    ->where('warehouse_id', $request->warehouse_id)
                    ->first();

                StokOpnameItem::create([
                    'stok_opname_id' => $opname->id,
                    'item_id'        => $row['item_id'],
                    'qty_sistem'     => $stock ? (float) $stock->qty : 0,
                    'qty_fisik'      => (float) $row['qty_fisik'],
                    'keterangan'     => $row['keterangan'] ?? null,
                ]);
            }

            return $nomor;
        });

        return response()->json(['success' => true, 'message' => "Dokumen {$nomor} berhasil dibuat"], 201);
    }

    // GET /stok-opname/{id}
    public function show(StokOpname $stokOpname)
    {
        $stokOpname->load(['warehouse', 'dibuatOleh', 'disetujuiOleh', 'items.item.category']);
        return response()->json(['success' => true, 'data' => $stokOpname]);
    }

    // PUT /stok-opname/{id} — hanya boleh edit kalau masih draft
    public function update(Request $request, StokOpname $stokOpname)
    {
        if ($stokOpname->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya dokumen draft yang bisa diedit'], 422);
        }

        $request->validate([
            'tipe'                  => 'required|string|max:100',
            'no_referensi'          => 'required|string|max:100',
            'keterangan'            => 'nullable|string',
            'tanggal_opname'        => 'required|date',
            'items'                 => 'required|array|min:1',
            'items.*.item_id'       => 'required|exists:items,id',
            'items.*.qty_fisik'     => 'required|numeric|min:0',
            'items.*.keterangan'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $stokOpname) {
            $stokOpname->update([
                'tipe'           => $request->tipe,
                'no_referensi'   => $request->no_referensi,
                'keterangan'     => $request->keterangan,
                'tanggal_opname' => $request->tanggal_opname,
            ]);

            // Hapus items lama, buat ulang
            $stokOpname->items()->delete();

            foreach ($request->items as $row) {
                $stock = ItemStock::where('item_id', $row['item_id'])
                    ->where('warehouse_id', $stokOpname->warehouse_id)
                    ->first();

                StokOpnameItem::create([
                    'stok_opname_id' => $stokOpname->id,
                    'item_id'        => $row['item_id'],
                    'qty_sistem'     => $stock ? (float) $stock->qty : 0,
                    'qty_fisik'      => (float) $row['qty_fisik'],
                    'keterangan'     => $row['keterangan'] ?? null,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil diperbarui']);
    }

    // POST /stok-opname/{id}/ajukan — submit untuk approval
    public function ajukan(StokOpname $stokOpname, Request $request)
    {
        if ($stokOpname->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya dokumen draft yang bisa diajukan'], 422);
        }

        // Cek user adalah pembuat
        if ($stokOpname->dibuat_oleh !== $request->user()->id && !$request->user()->isSuperuser()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak mengajukan dokumen ini'], 403);
        }

        $stokOpname->update([
            'status'       => 'menunggu_approval',
            'diajukan_at'  => now(),
        ]);

        return response()->json(['success' => true, 'message' => "Dokumen {$stokOpname->nomor} diajukan untuk persetujuan"]);
    }

    // POST /stok-opname/{id}/setujui — approve & langsung apply ke stok
    public function setujui(StokOpname $stokOpname, Request $request)
    {
        if ($stokOpname->status !== 'menunggu_approval') {
            return response()->json(['success' => false, 'message' => 'Dokumen tidak dalam status menunggu approval'], 422);
        }

        if (!$request->user()->isSuperuser() && !$request->user()->isAdminHO()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak menyetujui dokumen ini'], 403);
        }

        DB::transaction(function () use ($stokOpname, $request) {
            $stokOpname->load('items.item');

            $itemIndex = 1;
            foreach ($stokOpname->items as $row) {
                $selisih = (float) $row->qty_fisik - (float) $row->qty_sistem;
                if ($selisih == 0) { $itemIndex++; continue; }

                // Reference unik per item: ADJ-20260427-0001-001
                $refNo = $stokOpname->nomor . '-' . str_pad($itemIndex, 3, '0', STR_PAD_LEFT);

                // Terapkan penyesuaian stok via StockService
                $this->stockService->adjustment([
                    'item_id'        => $row->item_id,
                    'warehouse_id'   => $stokOpname->warehouse_id,
                    'qty'            => abs($selisih),
                    'type'           => $selisih > 0 ? 'in' : 'out',
                    'notes'          => "[{$stokOpname->tipe}] Ref: {$stokOpname->no_referensi} | Opname: {$stokOpname->nomor}",
                    'movement_date'  => $stokOpname->tanggal_opname->format('Y-m-d'),
                    'reference_no'   => $refNo,
                ], $request->user()->id);
                $itemIndex++;
            }

            $stokOpname->update([
                'status'        => 'disetujui',
                'disetujui_oleh' => $request->user()->id,
                'disetujui_at'  => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => "Dokumen {$stokOpname->nomor} disetujui dan stok sudah disesuaikan"]);
    }

    // POST /stok-opname/{id}/tolak
    public function tolak(StokOpname $stokOpname, Request $request)
    {
        if ($stokOpname->status !== 'menunggu_approval') {
            return response()->json(['success' => false, 'message' => 'Dokumen tidak dalam status menunggu approval'], 422);
        }

        if (!$request->user()->isSuperuser() && !$request->user()->isAdminHO()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak menolak dokumen ini'], 403);
        }

        $request->validate(['alasan_penolakan' => 'required|string|min:5']);

        $stokOpname->update([
            'status'            => 'ditolak',
            'alasan_penolakan'  => $request->alasan_penolakan,
            'disetujui_oleh'    => $request->user()->id,
            'disetujui_at'      => now(),
        ]);

        return response()->json(['success' => true, 'message' => "Dokumen {$stokOpname->nomor} ditolak"]);
    }

    // DELETE /stok-opname/{id} — hanya draft yang bisa dihapus
    public function destroy(StokOpname $stokOpname, Request $request)
    {
        if ($stokOpname->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya dokumen draft yang bisa dihapus'], 422);
        }

        if ($stokOpname->dibuat_oleh !== $request->user()->id && !$request->user()->isSuperuser()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak menghapus dokumen ini'], 403);
        }

        $stokOpname->delete();
        return response()->json(['success' => true, 'message' => 'Dokumen berhasil dihapus']);
    }
}