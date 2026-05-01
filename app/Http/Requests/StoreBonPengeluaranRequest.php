<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBonPengeluaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_request_id'    => 'nullable|exists:material_requests,id',
            'permintaan_material_id' => 'nullable|exists:permintaan_material,id',
            'warehouse_id'           => 'required|exists:warehouses,id',
            'received_by'            => 'required|string|max:255',
            'issue_date'             => 'required|date',
            'notes'                  => 'nullable|string',
            'unit_code'              => 'nullable|string|max:50',
            'unit_type'              => 'nullable|string|max:100',
            'hm_km'                  => 'nullable|numeric',
            'mechanic'               => 'nullable|string|max:150',
            'po_number'              => 'nullable|string|max:100',
            'auto_issue'             => 'nullable|boolean',
            'items'                  => 'required|array|min:1',
            'items.*.item_id'        => 'nullable|exists:items,id',
            'items.*.nama_barang'    => 'required|string|max:255',
            'items.*.qty'            => 'required|numeric|min:0.01',
            'items.*.satuan'         => 'required|string|max:50',
            'items.*.keterangan'     => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required'        => 'Gudang wajib dipilih.',
            'warehouse_id.exists'          => 'Gudang tidak ditemukan.',
            'received_by.required'         => 'Nama penerima wajib diisi.',
            'issue_date.required'          => 'Tanggal pengeluaran wajib diisi.',
            'issue_date.date'              => 'Format tanggal tidak valid.',
            'items.required'               => 'Minimal 1 barang harus ditambahkan.',
            'items.min'                    => 'Minimal 1 barang harus ditambahkan.',
            'items.*.nama_barang.required' => 'Nama barang wajib diisi.',
            'items.*.qty.required'         => 'Jumlah barang wajib diisi.',
            'items.*.qty.min'              => 'Jumlah barang harus lebih dari 0.',
            'items.*.satuan.required'      => 'Satuan wajib diisi.',
        ];
    }
}
