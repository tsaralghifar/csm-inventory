<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImportSaldoAwalService;
use Illuminate\Http\Request;

class ImportSaldoAwalController extends Controller
{
    public function __construct(protected ImportSaldoAwalService $service) {}

    public function preview(Request $request)
    {
        $this->authorize('manage-items');

        $request->validate([
            'file'         => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'warehouse_id' => 'required|exists:warehouses,id',
            'sheet_name'   => 'nullable|string',
            'auto_create'  => 'boolean',
        ]);

        try {
            $result = $this->service->preview(
                filePath:    $request->file('file')->getPathname(),
                warehouseId: (int) $request->warehouse_id,
                sheetName:   $request->sheet_name ?? 'JAN',
                autoCreate:  $request->boolean('auto_create', false),
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membaca file: ' . $e->getMessage()], 422);
        }
    }

    public function import(Request $request)
    {
        $this->authorize('manage-items');

        $request->validate([
            'file'          => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'sheet_name'    => 'nullable|string',
            'tanggal_saldo' => 'required|date',
            'overwrite'     => 'boolean',
            'auto_create'   => 'boolean',
            'category_id'   => 'required_if:auto_create,true|nullable|exists:categories,id',
        ]);

        try {
            $result = $this->service->import(
                filePath:   $request->file('file')->getPathname(),
                warehouseId: (int) $request->warehouse_id,
                sheetName:  $request->sheet_name ?? 'JAN',
                tanggal:    $request->tanggal_saldo,
                userId:     $request->user()->id,
                overwrite:  $request->boolean('overwrite', false),
                autoCreate: $request->boolean('auto_create', false),
                categoryId: $request->category_id ? (int) $request->category_id : null,
            );

            $message = "Import selesai. {$result['imported']} stok diimport";
            if ($result['created'] > 0) $message .= ", {$result['created']} barang baru dibuat";
            if ($result['skipped'] > 0) $message .= ", {$result['skipped']} dilewati";
            $message .= '.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Import gagal: ' . $e->getMessage()], 422);
        }
    }
}