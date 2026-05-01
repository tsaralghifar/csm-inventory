<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_request_id'                   => 'nullable|exists:material_requests,id',
            'permintaan_material_ids'               => 'nullable|array|min:1',
            'permintaan_material_ids.*'             => 'exists:permintaan_material,id',
            'permintaan_material_id'                => 'nullable|exists:permintaan_material,id',
            'warehouse_id'                          => 'required|exists:warehouses,id',
            'vendor_name'                           => 'required|string|max:255',
            'vendor_contact'                        => 'nullable|string|max:255',
            'expected_date'                         => 'nullable|date',
            'notes'                                 => 'nullable|string',
            'ppn_percent'                           => 'nullable|numeric|min:0|max:100',
            'diskon_persen'                         => 'nullable|numeric|min:0|max:100',
            'items'                                 => 'required|array|min:1',
            'items.*.item_id'                       => 'nullable|exists:items,id',
            'items.*.permintaan_material_item_id'   => 'nullable|exists:permintaan_material_items,id',
            'items.*.qty_pm'                        => 'nullable|numeric|min:0',
            'items.*.part_number'                   => 'nullable|string|max:100',
            'items.*.nama_barang'                   => 'required|string|max:255',
            'items.*.kode_unit'                     => 'nullable|string',
            'items.*.tipe_unit'                     => 'nullable|string',
            'items.*.qty'                           => 'required|numeric|min:0.01',
            'items.*.satuan'                        => 'required|string',
            'items.*.harga_satuan'                  => 'nullable|numeric|min:0',
            'items.*.diskon_persen'                 => 'nullable|numeric|min:0|max:100',
            'items.*.keterangan'                    => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required'         => 'Gudang wajib dipilih.',
            'warehouse_id.exists'           => 'Gudang tidak ditemukan.',
            'vendor_name.required'          => 'Nama vendor wajib diisi.',
            'items.required'                => 'Minimal 1 item harus ditambahkan.',
            'items.min'                     => 'Minimal 1 item harus ditambahkan.',
            'items.*.nama_barang.required'  => 'Nama barang wajib diisi.',
            'items.*.qty.required'          => 'Jumlah barang wajib diisi.',
            'items.*.qty.min'               => 'Jumlah barang harus lebih dari 0.',
            'items.*.satuan.required'       => 'Satuan wajib diisi.',
            'ppn_percent.max'               => 'PPN tidak boleh lebih dari 100%.',
            'diskon_persen.max'             => 'Diskon tidak boleh lebih dari 100%.',
        ];
    }
}
