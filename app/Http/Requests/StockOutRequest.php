<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-stock-out');
    }

    public function rules(): array
    {
        return [
            'warehouse_id'  => 'required|exists:warehouses,id',
            'qty'           => 'required|numeric|min:0.01',
            'unit_code'     => 'nullable|string',
            'unit_type'     => 'nullable|string',
            'hm_km'         => 'nullable|numeric',
            'po_number'     => 'nullable|string',
            'mechanic'      => 'nullable|string',
            'site_name'     => 'nullable|string',
            'notes'         => 'nullable|string',
            'movement_date' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required'  => 'Gudang wajib dipilih.',
            'warehouse_id.exists'    => 'Gudang tidak ditemukan.',
            'qty.required'           => 'Jumlah stok wajib diisi.',
            'qty.min'                => 'Jumlah stok harus lebih dari 0.',
            'movement_date.required' => 'Tanggal wajib diisi.',
            'movement_date.date'     => 'Format tanggal tidak valid.',
        ];
    }
}
