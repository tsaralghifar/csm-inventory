<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPriceHistory;
use App\Models\ItemStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSaldoAwalController extends Controller
{
    private const DATA_START_ROW = 8;

    public function preview(Request $request)
    {
        $this->authorize('manage-items');

        $request->validate([
            'file'         => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'warehouse_id' => 'required|exists:warehouses,id',
            'sheet_name'   => 'nullable|string',
            'auto_create'  => 'boolean',
        ]);

        $sheetName  = $request->sheet_name ?? 'JAN';
        $autoCreate = $request->boolean('auto_create', false);

        try {
            [$rows, $sheetTitle] = $this->readExcel($request->file('file')->getPathname(), $sheetName);

            $preview  = [];
            $errors   = [];
            $rowCount = 0;

            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex < self::DATA_START_ROW) continue;

                $partNumber = trim((string)($row['B'] ?? ''));
                $namaBarang = trim((string)($row['C'] ?? ''));

                if ($partNumber === '' && $namaBarang === '') continue;

                $stokAkhir  = $this->parseNumber($row['N'] ?? null);
                $harga      = $this->parseNumber($row['O'] ?? null);
                $totalHarga = $this->parseNumber($row['P'] ?? null);

                if ($stokAkhir === null || $stokAkhir < 0) continue;
                if ($stokAkhir == 0 && $harga === null) continue;

                if ($totalHarga === null && $stokAkhir !== null && $harga !== null) {
                    $totalHarga = $stokAkhir * $harga;
                }

                $rowCount++;

                // Cari item existing
                $item = null;
                if ($partNumber) $item = Item::where('part_number', $partNumber)->first();
                if (!$item && $namaBarang) $item = Item::where('name', 'ilike', $namaBarang)->first();

                if ($item) {
                    $stock = ItemStock::where('item_id', $item->id)
                        ->where('warehouse_id', $request->warehouse_id)->first();
                    $currentStock = $stock ? (float) $stock->qty : 0;
                    $status = 'found';
                } elseif ($autoCreate) {
                    $status = 'will_create';
                    $currentStock = null;
                    // Tidak masukkan ke errors jika auto_create aktif
                } else {
                    $errors[] = "Baris {$rowIndex}: '{$namaBarang}'" .
                        ($partNumber ? " (part: {$partNumber})" : '') . " tidak ditemukan";
                    $currentStock = null;
                    $status = 'not_found';
                }

                $preview[] = [
                    'row'           => $rowIndex,
                    'part_number'   => $partNumber,
                    'nama_barang'   => $namaBarang,
                    'stok_akhir'    => $stokAkhir,
                    'harga'         => $harga,
                    'total_harga'   => $totalHarga,
                    'item_id'       => $item?->id,
                    'item_name_db'  => $item?->name,
                    'current_stock' => $currentStock,
                    'status'        => $status,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'preview'      => $preview,
                    'total_rows'   => $rowCount,
                    'found'        => collect($preview)->where('status', 'found')->count(),
                    'will_create'  => collect($preview)->where('status', 'will_create')->count(),
                    'not_found'    => collect($preview)->where('status', 'not_found')->count(),
                    'sheet_used'   => $sheetTitle,
                    'errors'       => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membaca file: ' . $e->getMessage()], 422);
        }
    }

    public function import(Request $request)
    {
        $this->authorize('manage-items');

        $request->validate([
            'file'           => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'warehouse_id'   => 'required|exists:warehouses,id',
            'sheet_name'     => 'nullable|string',
            'tanggal_saldo'  => 'required|date',
            'overwrite'      => 'boolean',
            'auto_create'    => 'boolean',
            'category_id'    => 'required_if:auto_create,true|nullable|exists:categories,id',
        ]);

        $warehouseId = $request->warehouse_id;
        $tanggal     = $request->tanggal_saldo;
        $overwrite   = $request->boolean('overwrite', false);
        $autoCreate  = $request->boolean('auto_create', false);
        $categoryId  = $request->category_id;

        try {
            [$rows] = $this->readExcel(
                $request->file('file')->getPathname(),
                $request->sheet_name ?? 'JAN'
            );

            $imported      = 0;
            $created       = 0;
            $skipped       = 0;
            $failed        = [];

            DB::transaction(function () use (
                $rows, $warehouseId, $tanggal, $overwrite, $autoCreate, $categoryId,
                &$imported, &$created, &$skipped, &$failed, $request
            ) {
                $userId = $request->user()->id;

                foreach ($rows as $rowIndex => $row) {
                    if ($rowIndex < self::DATA_START_ROW) continue;

                    $partNumber = trim((string)($row['B'] ?? ''));
                    $namaBarang = trim((string)($row['C'] ?? ''));
                    $isNewItem  = false;

                    if ($partNumber === '' && $namaBarang === '') continue;

                    $stokAkhir = $this->parseNumber($row['N'] ?? null);
                    $harga     = $this->parseNumber($row['O'] ?? null);

                    if ($stokAkhir === null || $stokAkhir < 0) continue;
                    if ($stokAkhir == 0 && $harga === null) continue;

                    // Cari item existing
                    $item = null;
                    if ($partNumber) $item = Item::where('part_number', $partNumber)->first();
                    if (!$item && $namaBarang) $item = Item::where('name', 'ilike', $namaBarang)->first();

                    // Auto-create jika tidak ditemukan
                    if (!$item) {
                        if (!$autoCreate) {
                            $failed[] = "Baris {$rowIndex}: '{$namaBarang}' tidak ditemukan, dilewati.";
                            $skipped++;
                            continue;
                        }

                        // Buat part_number unik jika kosong atau duplikat
                        $pn = $partNumber ?: $this->generatePartNumber($namaBarang);
                        // Pastikan part_number unik
                        if (Item::where('part_number', $pn)->exists()) {
                            $pn = $pn . '-' . $rowIndex;
                        }

                        $item = Item::create([
                            'part_number' => $pn,
                            'name'        => $namaBarang ?: $partNumber,
                            'category_id' => $categoryId,
                            'unit'        => 'PCS',
                            'min_stock'   => 0,
                            'price'       => $harga ?? 0,
                            'is_active'   => true,
                        ]);

                        $created++;
                        $isNewItem = true;
                    }

                    // Cek stok saat ini
                    // Barang baru (baru di-create) selalu diisi stok — tidak perlu cek overwrite
                    $itemStock = ItemStock::where('item_id', $item->id)
                        ->where('warehouse_id', $warehouseId)->first();

                    if (!($isNewItem ?? false) && $itemStock && $itemStock->qty > 0 && !$overwrite) {
                        $skipped++;
                        continue;
                    }

                    $qtyBefore = $itemStock ? (float) $itemStock->qty : 0;

                    ItemStock::updateOrCreate(
                        ['item_id' => $item->id, 'warehouse_id' => $warehouseId],
                        ['qty' => $stokAkhir, 'avg_price' => $harga ?? 0, 'last_updated' => now()]
                    );

                    if ($harga && $harga > 0) {
                        $item->update(['price' => $harga]);
                        ItemPriceHistory::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $warehouseId,
                            'purchase_price'   => $harga,
                            'avg_price_before' => $qtyBefore > 0 ? ((float) $item->price) : 0,
                            'avg_price_after'  => $harga,
                            'qty_received'     => $stokAkhir,
                            'reference_no'     => 'SALDO-AWAL-' . date('Y', strtotime($tanggal)),
                            'source_type'      => 'saldo_awal',
                            'created_by'       => $userId,
                            'transaction_date' => $tanggal,
                        ]);
                    }

                    $prefix  = 'ADJ-' . date('Ymd') . '-';
                    $lastRef = StockMovement::where('reference_no', 'like', "{$prefix}%")
                        ->orderByRaw('CAST(SUBSTRING(reference_no FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
                        ->value('reference_no');
                    $refNo = $prefix . str_pad(
                        ($lastRef ? (int) substr($lastRef, strlen($prefix)) : 0) + 1,
                        4, '0', STR_PAD_LEFT
                    );

                    StockMovement::create([
                        'reference_no'      => $refNo,
                        'type'              => 'adjustment',
                        'item_id'           => $item->id,
                        'from_warehouse_id' => $warehouseId,
                        'qty'               => abs($stokAkhir - $qtyBefore),
                        'qty_before'        => $qtyBefore,
                        'qty_after'         => $stokAkhir,
                        'price'             => $harga ?? 0,
                        'notes'             => 'Import saldo awal per ' . $tanggal,
                        'moveable_type'     => 'saldo_awal',
                        'moveable_id'       => 0,
                        'created_by'        => $userId,
                        'movement_date'     => $tanggal,
                    ]);

                    $imported++;
                }
            });

            $message = "Import selesai. {$imported} stok diimport";
            if ($created > 0) $message .= ", {$created} barang baru dibuat";
            if ($skipped > 0) $message .= ", {$skipped} dilewati";
            $message .= ".";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => [
                    'imported' => $imported,
                    'created'  => $created,
                    'skipped'  => $skipped,
                    'failed'   => $failed,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Import gagal: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Generate part number dari nama barang jika kolom B kosong
     */
    private function generatePartNumber(string $name): string
    {
        // Ambil huruf kapital + angka dari nama, max 20 karakter
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9\-\/]/', '-', $name));
        $clean = preg_replace('/-+/', '-', trim($clean, '-'));
        return substr($clean, 0, 50) ?: 'ITEM-' . time();
    }

    private function readExcel(string $path, string $sheetName): array
    {
        $prevMemory = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $readerType = IOFactory::identify($path);
            $reader     = IOFactory::createReader($readerType);

            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(false);
            }

            $spreadsheet = $reader->load($path);

            $sheet = null;
            foreach ($spreadsheet->getSheetNames() as $name) {
                if (strtolower($name) === strtolower($sheetName)) {
                    $sheet = $spreadsheet->getSheetByName($name);
                    break;
                }
            }
            if (!$sheet) $sheet = $spreadsheet->getActiveSheet();

            $sheetTitle = $sheet->getTitle();
            $highRow    = $sheet->getHighestDataRow();

            $rows = [];
            for ($r = 1; $r <= $highRow; $r++) {
                foreach (['B', 'C', 'N', 'O', 'P'] as $col) {
                    $rows[$r][$col] = $sheet->getCell($col . $r)->getCalculatedValue();
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [$rows, $sheetTitle];
        } finally {
            ini_set('memory_limit', $prevMemory);
        }
    }

    private function parseNumber($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;
        if (is_numeric($value)) return (float) $value;

        $str = preg_replace('/\s+/', '', (string) $value);
        if ($str === '' || $str === '-') return null;

        // Format Indonesia: 1.038.889 atau 1.038.889,50
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $str)) {
            return (float) str_replace(',', '.', str_replace('.', '', $str));
        }
        // Format ribuan koma: 1,038,889
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d*)?$/', $str)) {
            return (float) str_replace(',', '', $str);
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', $str);
        return is_numeric($cleaned) && $cleaned !== '' ? (float) $cleaned : null;
    }
}