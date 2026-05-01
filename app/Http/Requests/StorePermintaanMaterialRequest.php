<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StorePermintaanMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'              => 'required|exists:warehouses,id',
            'type'                      => 'nullable|in:part,office',
            'notes'                     => 'nullable|string',
            'needed_date'               => 'nullable|date',
            'items'                     => 'required|array|min:1',
            'items.*.item_id'           => 'nullable|exists:items,id',
            'items.*.part_number'       => 'nullable|string|max:100',
            'items.*.nama_barang'       => 'required|string|max:255',
            'items.*.kode_unit'         => 'nullable|string|max:100',
            'items.*.tipe_unit'         => 'nullable|string|max:100',
            'items.*.qty'               => 'required|numeric|min:0.01',
            'items.*.satuan'            => 'required|string|max:50',
            'items.*.keterangan'        => 'nullable|string',
            'items.*.is_new_item'       => 'nullable|boolean',
            'items.*.new_part_number'   => 'nullable|string|max:100',
            'items.*.new_category_id'   => 'nullable|exists:categories,id',
            'items.*.new_brand'         => 'nullable|string|max:100',
            'items.*.new_min_stock'     => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required'        => 'Gudang wajib dipilih.',
            'warehouse_id.exists'          => 'Gudang tidak ditemukan.',
            'type.in'                      => 'Tipe harus part atau office.',
            'items.required'               => 'Minimal 1 barang harus ditambahkan.',
            'items.min'                    => 'Minimal 1 barang harus ditambahkan.',
            'items.*.nama_barang.required' => 'Nama barang wajib diisi.',
            'items.*.qty.required'         => 'Jumlah barang wajib diisi.',
            'items.*.qty.min'              => 'Jumlah barang harus lebih dari 0.',
            'items.*.satuan.required'      => 'Satuan wajib diisi.',
        ];
    }

    /**
     * Validasi tambahan setelah rules() — cek field wajib untuk barang baru.
     * Ini menggantikan loop ValidationException manual di controller.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('items', []) as $idx => $item) {
                if (!empty($item['is_new_item'])) {
                    if (empty($item['new_part_number'])) {
                        $validator->errors()->add(
                            "items.{$idx}.new_part_number",
                            'Part Number wajib diisi untuk barang baru.'
                        );
                    }
                    if (empty($item['new_category_id'])) {
                        $validator->errors()->add(
                            "items.{$idx}.new_category_id",
                            'Kategori wajib dipilih untuk barang baru.'
                        );
                    }
                }
            }
        });
    }
}
