<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id'                 => 'required|exists:purchase_orders,id',
            'warehouse_id'                      => 'required|exists:warehouses,id',
            'vendor_name'                       => 'required|string|max:255',
            'vendor_contact'                    => 'nullable|string|max:255',
            'retur_date'                        => 'required|date',
            'alasan'                            => 'nullable|string',
            'notes'                             => 'nullable|string',
            'items'                             => 'required|array|min:1',
            'items.*.item_id'                   => 'nullable|exists:items,id',
            'items.*.purchase_order_item_id'    => 'nullable|exists:purchase_order_items,id',
            'items.*.nama_barang'               => 'required|string|max:255',
            'items.*.part_number'               => 'nullable|string|max:100',
            'items.*.kode_unit'                 => 'nullable|string|max:100',
            'items.*.tipe_unit'                 => 'nullable|string|max:100',
            'items.*.qty'                       => 'required|numeric|min:0.01',
            'items.*.satuan'                    => 'required|string|max:50',
            'items.*.harga_satuan'              => 'nullable|numeric|min:0',
            'items.*.jenis'                     => 'required|in:returnable,non_returnable',
            'items.*.alasan_item'               => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_order_id.required'    => 'Purchase Order wajib dipilih.',
            'purchase_order_id.exists'      => 'Purchase Order tidak ditemukan.',
            'warehouse_id.required'         => 'Gudang wajib dipilih.',
            'vendor_name.required'          => 'Nama vendor wajib diisi.',
            'retur_date.required'           => 'Tanggal retur wajib diisi.',
            'items.required'                => 'Minimal 1 item harus ditambahkan.',
            'items.*.nama_barang.required'  => 'Nama barang wajib diisi.',
            'items.*.qty.required'          => 'Jumlah wajib diisi.',
            'items.*.qty.min'               => 'Jumlah harus lebih dari 0.',
            'items.*.satuan.required'       => 'Satuan wajib diisi.',
            'items.*.jenis.required'        => 'Jenis retur wajib dipilih.',
            'items.*.jenis.in'              => 'Jenis retur harus returnable atau non_returnable.',
        ];
    }
}
