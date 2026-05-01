<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-items');
    }

    public function rules(): array
    {
        $itemId = $this->route('item')?->id;

        return [
            'part_number'   => "sometimes|string|max:100|unique:items,part_number,{$itemId}",
            'name'          => 'sometimes|string|max:255',
            'category_id'   => 'sometimes|exists:categories,id',
            'brand'         => 'nullable|string',
            'unit'          => 'sometimes|string|max:20',
            'min_stock'     => 'sometimes|numeric|min:0',
            'price'         => 'nullable|numeric|min:0',
            'location_code' => 'nullable|string',
            'description'   => 'nullable|string',
            'is_active'     => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'part_number.unique' => 'Part number sudah digunakan oleh barang lain.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
            'min_stock.numeric'  => 'Stok minimum harus berupa angka.',
        ];
    }
}
