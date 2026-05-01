<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuratJalanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id'                      => 'required|exists:purchase_orders,id',
            'warehouse_id'                           => 'required|exists:warehouses,id',
            'vendor_name'                            => 'nullable|string|max:255',
            'driver_name'                            => 'nullable|string|max:255',
            'vehicle_plate'                          => 'nullable|string|max:50',
            'received_date'                          => 'required|date',
            'notes'                                  => 'nullable|string',
            'items'                                  => 'required|array|min:1',
            'items.*.purchase_order_item_id'         => 'required|exists:purchase_order_items,id',
            'items.*.item_id'                        => 'nullable|exists:items,id',
            'items.*.qty_received'                   => 'required|numeric|min:0.01',
            'items.*.masuk_stok'                     => 'boolean',
            'items.*.keterangan'                     => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_order_id.required'             => 'Purchase Order wajib dipilih.',
            'purchase_order_id.exists'               => 'Purchase Order tidak ditemukan.',
            'warehouse_id.required'                  => 'Gudang wajib dipilih.',
            'received_date.required'                 => 'Tanggal penerimaan wajib diisi.',
            'received_date.date'                     => 'Format tanggal tidak valid.',
            'items.required'                         => 'Minimal 1 item harus diisi.',
            'items.min'                              => 'Minimal 1 item harus diisi.',
            'items.*.purchase_order_item_id.required'=> 'Item PO wajib dipilih.',
            'items.*.purchase_order_item_id.exists'  => 'Item PO tidak ditemukan.',
            'items.*.qty_received.required'          => 'Jumlah diterima wajib diisi.',
            'items.*.qty_received.min'               => 'Jumlah diterima harus lebih dari 0.',
        ];
    }
}
