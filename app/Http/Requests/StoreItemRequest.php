<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-items');
    }

    public function rules(): array
    {
        return [
            'part_number'   => 'required|string|max:100|unique:items',
            'name'          => 'required|string|max:255',
            'category_id'   => 'required|exists:categories,id',
            'brand'         => 'nullable|string',
            'unit'          => 'required|string|max:20',
            'min_stock'     => 'required|numeric|min:0',
            'price'         => 'nullable|numeric|min:0',
            'location_code' => 'nullable|string',
            'description'   => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'part_number.required' => 'Part number wajib diisi.',
            'part_number.unique'   => 'Part number sudah digunakan.',
            'name.required'        => 'Nama barang wajib diisi.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists'   => 'Kategori tidak ditemukan.',
            'unit.required'        => 'Satuan wajib diisi.',
            'min_stock.required'   => 'Stok minimum wajib diisi.',
            'min_stock.numeric'    => 'Stok minimum harus berupa angka.',
        ];
    }
}
