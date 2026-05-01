<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-warehouses');
    }

    public function rules(): array
    {
        return [
            'code'      => 'required|string|max:20|unique:warehouses',
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:ho,site',
            'location'  => 'nullable|string',
            'address'   => 'nullable|string',
            'pic_name'  => 'nullable|string',
            'pic_phone' => 'nullable|string|max:20',
            'notes'     => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'  => 'Kode gudang wajib diisi.',
            'code.unique'    => 'Kode gudang sudah digunakan.',
            'name.required'  => 'Nama gudang wajib diisi.',
            'type.required'  => 'Tipe gudang wajib dipilih.',
            'type.in'        => 'Tipe gudang harus ho atau site.',
        ];
    }
}
