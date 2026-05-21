<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPriceHistory;
use App\Models\ItemStock;
use App\Models\StockLayer;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSaldoAwalService
{
    private const DATA_START_ROW = 8;

    /**
     * Baca file Excel dan kembalikan preview per baris tanpa mutasi database.
     *
     * @return array{
     *   preview: list<array>,
     *   total_rows: int,
     *   found: int,
     *   will_create: int,
     *   not_found: int,
     *   sheet_used: string,
     *   errors: list<string>
     * }
     */
    public function preview(string $filePath, int $warehouseId, string $sheetName = 'JAN', bool $autoCreate = false): array
    {
        [$rows, $sheetTitle] = $this->readExcel($filePath, $sheetName);

        $preview  = [];
        $errors   = [];
        $rowCount = 0;

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex < self::DATA_START_ROW) continue;

            $partNumber = trim((string) ($row['B'] ?? ''));
            $namaBarang = trim((string) ($row['C'] ?? ''));

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

            $item = $this->resolveItem($partNumber, $namaBarang);

            if ($item) {
                $stock        = ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseId)->first();
                $currentStock = $stock ? (float) $stock->qty : 0;
                $status       = 'found';
            } elseif ($autoCreate) {
                $status       = 'will_create';
                $currentStock = null;
            } else {
                $errors[] = "Baris {$rowIndex}: '{$namaBarang}'"
                    . ($partNumber ? " (part: {$partNumber})" : '') . ' tidak ditemukan';
                $currentStock = null;
                $status       = 'not_found';
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

        $previewCollection = collect($preview);

        return [
            'preview'     => $preview,
            'total_rows'  => $rowCount,
            'found'       => $previewCollection->where('status', 'found')->count(),
            'will_create' => $previewCollection->where('status', 'will_create')->count(),
            'not_found'   => $previewCollection->where('status', 'not_found')->count(),
            'sheet_used'  => $sheetTitle,
            'errors'      => $errors,
        ];
    }

    /**
     * Jalankan import saldo awal ke database dalam satu transaction.
     *
     * @return array{imported: int, created: int, skipped: int, failed: list<string>}
     */
    public function import(
        string $filePath,
        int $warehouseId,
        string $sheetName,
        string $tanggal,
        int $userId,
        bool $overwrite = false,
        bool $autoCreate = false,
        ?int $categoryId = null,
    ): array {
        [$rows] = $this->readExcel($filePath, $sheetName);

        $imported = 0;
        $created  = 0;
        $skipped  = 0;
        $failed   = [];

        DB::transaction(function () use (
            $rows, $warehouseId, $tanggal, $userId, $overwrite, $autoCreate, $categoryId,
            &$imported, &$created, &$skipped, &$failed
        ) {
            // Hitung dulu berapa baris yang akan diproses agar bisa reserve blok sequence
            // sekaligus dengan satu lock, menghindari race condition antar import paralel.
            $validRowCount = 0;
            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex < self::DATA_START_ROW) continue;
                $partNumber = trim((string) ($row['B'] ?? ''));
                $namaBarang = trim((string) ($row['C'] ?? ''));
                if ($partNumber === '' && $namaBarang === '') continue;
                $stokAkhir = $this->parseNumber($row['N'] ?? null);
                $harga     = $this->parseNumber($row['O'] ?? null);
                if ($stokAkhir === null || $stokAkhir < 0) continue;
                if ($stokAkhir == 0 && $harga === null) continue;
                $validRowCount++;
            }

            // Reservasi blok nomor urut sekaligus dengan satu lockForUpdate
            $prefix  = 'ADJ-' . now()->format('Ymd') . '-';
            $lastRef = StockMovement::lockForUpdate()
                ->where('reference_no', 'like', "{$prefix}%")
                ->orderByRaw('CAST(SUBSTRING(reference_no FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
                ->value('reference_no');
            $nextNumber = $lastRef ? ((int) substr($lastRef, strlen($prefix)) + 1) : 1;

            // Pool refNo yang sudah di-generate di awal, tinggal ambil satu per satu
            $refNoPool = [];
            for ($i = 0; $i < $validRowCount; $i++) {
                $refNoPool[] = $prefix . str_pad($nextNumber + $i, 4, '0', STR_PAD_LEFT);
            }
            $refNoIndex = 0;

            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex < self::DATA_START_ROW) continue;

                $partNumber = trim((string) ($row['B'] ?? ''));
                $namaBarang = trim((string) ($row['C'] ?? ''));

                if ($partNumber === '' && $namaBarang === '') continue;

                $stokAkhir = $this->parseNumber($row['N'] ?? null);
                $harga     = $this->parseNumber($row['O'] ?? null);

                if ($stokAkhir === null || $stokAkhir < 0) continue;
                if ($stokAkhir == 0 && $harga === null) continue;

                $item      = $this->resolveItem($partNumber, $namaBarang);
                $isNewItem = false;

                if (!$item) {
                    if (!$autoCreate) {
                        $failed[] = "Baris {$rowIndex}: '{$namaBarang}' tidak ditemukan, dilewati.";
                        $skipped++;
                        continue;
                    }

                    $item      = $this->createItem($partNumber, $namaBarang, $harga, $categoryId, $rowIndex);
                    $isNewItem = true;
                    $created++;
                }

                $itemStock = ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseId)->first();

                if (!$isNewItem && $itemStock && $itemStock->qty > 0 && !$overwrite) {
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
                        'avg_price_before' => $qtyBefore > 0 ? (float) $item->price : 0,
                        'avg_price_after'  => $harga,
                        'qty_received'     => $stokAkhir,
                        'reference_no'     => 'SALDO-AWAL-' . date('Y', strtotime($tanggal)),
                        'source_type'      => 'saldo_awal',
                        'created_by'       => $userId,
                        'transaction_date' => $tanggal,
                    ]);
                }

                StockMovement::create([
                    'reference_no'      => $refNoPool[$refNoIndex++],
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

                // ── Buat FIFO Layer untuk saldo awal ─────────────────────────
                // Hapus layer lama item ini di gudang ini dulu (overwrite)
                StockLayer::where('item_id', $item->id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('source_type', 'import')
                    ->delete();

                if ($stokAkhir > 0) {
                    StockLayer::create([
                        'item_id'       => $item->id,
                        'warehouse_id'  => $warehouseId,
                        'qty_awal'      => $stokAkhir,
                        'qty_sisa'      => $stokAkhir,
                        'harga_satuan'  => $harga ?? 0,
                        'tanggal_masuk' => $tanggal,
                        'source_type'   => 'import',
                        'reference_no'  => 'SALDO-AWAL-' . date('Y', strtotime($tanggal)),
                        'created_by'    => $userId,
                    ]);
                }

                $imported++;
            }
        });

        return compact('imported', 'created', 'skipped', 'failed');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Cari item di master berdasarkan part_number lalu nama barang (case-insensitive).
     */
    private function resolveItem(string $partNumber, string $namaBarang): ?Item
    {
        $item = null;
        if ($partNumber) $item = Item::where('part_number', $partNumber)->first();
        if (!$item && $namaBarang) $item = Item::where('name', 'ilike', $namaBarang)->first();
        return $item;
    }

    /**
     * Buat item baru di master dengan part_number yang dijamin unik.
     */
    private function createItem(string $partNumber, string $namaBarang, ?float $harga, ?int $categoryId, int $rowIndex): Item
    {
        $pn = $partNumber ?: $this->generatePartNumber($namaBarang);

        if (Item::where('part_number', $pn)->exists()) {
            $pn = $pn . '-' . $rowIndex;
        }

        return Item::create([
            'part_number' => $pn,
            'name'        => $namaBarang ?: $partNumber,
            'category_id' => $categoryId,
            'unit'        => 'PCS',
            'min_stock'   => 0,
            'price'       => $harga ?? 0,
            'is_active'   => true,
        ]);
    }

    /**
     * Generate part_number dari nama barang: ambil karakter alfanumerik, max 50 char.
     */
    private function generatePartNumber(string $name): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9\-\/]/', '-', $name));
        $clean = preg_replace('/-+/', '-', trim($clean, '-'));
        return substr($clean, 0, 50) ?: 'ITEM-' . time();
    }

    /**
     * Generate reference number untuk StockMovement adjustment dengan database lock.
     */
    private function generateAdjRefNo(): string
    {
        $prefix  = 'ADJ-' . now()->format('Ymd') . '-';
        $lastRef = StockMovement::lockForUpdate()
            ->where('reference_no', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(reference_no FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('reference_no');

        $lastNumber = $lastRef ? (int) substr($lastRef, strlen($prefix)) : 0;

        return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Baca file Excel, ekstrak hanya kolom yang dibutuhkan (B, C, N, O, P).
     * Menaikkan memory limit sementara untuk file besar.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
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

    /**
     * Parse angka dari berbagai format: integer, desimal, format ribuan Indonesia (1.038.889,50),
     * format ribuan koma (1,038,889), dsb.
     */
    private function parseNumber(mixed $value): ?float
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