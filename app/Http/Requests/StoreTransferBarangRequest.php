<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization ditangani di controller/service
    }

    public function rules(): array
    {
        return [
            'from_warehouse_id'     => 'required|exists:warehouses,id',
            'to_warehouse_id'       => 'required|exists:warehouses,id|different:from_warehouse_id',
            'needed_date'           => 'nullable|date',
            'notes'                 => 'nullable|string',
            'items'                 => 'required|array|min:1',
            'items.*.item_id'       => 'required|exists:items,id',
            'items.*.qty'           => 'required|numeric|min:0.01',
            'items.*.keterangan'    => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'from_warehouse_id.required'    => 'Gudang asal wajib dipilih.',
            'from_warehouse_id.exists'      => 'Gudang asal tidak ditemukan.',
            'to_warehouse_id.required'      => 'Gudang tujuan wajib dipilih.',
            'to_warehouse_id.exists'        => 'Gudang tujuan tidak ditemukan.',
            'to_warehouse_id.different'     => 'Gudang tujuan tidak boleh sama dengan gudang asal.',
            'items.required'                => 'Minimal 1 barang harus ditambahkan.',
            'items.min'                     => 'Minimal 1 barang harus ditambahkan.',
            'items.*.item_id.required'      => 'Barang wajib dipilih.',
            'items.*.item_id.exists'        => 'Barang tidak ditemukan.',
            'items.*.qty.required'          => 'Jumlah barang wajib diisi.',
            'items.*.qty.min'               => 'Jumlah barang harus lebih dari 0.',
        ];
    }
}
