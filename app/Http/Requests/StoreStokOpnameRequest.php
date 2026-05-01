<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStokOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'       => 'required|exists:warehouses,id',
            'tipe'               => 'required|string|max:100',
            'no_referensi'       => 'required|string|max:100',
            'keterangan'         => 'nullable|string',
            'tanggal_opname'     => 'required|date',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:items,id',
            'items.*.qty_fisik'  => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required'       => 'Gudang wajib dipilih.',
            'tipe.required'               => 'Tipe opname wajib diisi.',
            'no_referensi.required'       => 'No. referensi wajib diisi.',
            'tanggal_opname.required'     => 'Tanggal opname wajib diisi.',
            'tanggal_opname.date'         => 'Format tanggal tidak valid.',
            'items.required'              => 'Minimal 1 barang harus ditambahkan.',
            'items.min'                   => 'Minimal 1 barang harus ditambahkan.',
            'items.*.item_id.required'    => 'Barang wajib dipilih.',
            'items.*.item_id.exists'      => 'Barang tidak ditemukan.',
            'items.*.qty_fisik.required'  => 'Qty fisik wajib diisi.',
            'items.*.qty_fisik.min'       => 'Qty fisik tidak boleh negatif.',
        ];
    }
}
