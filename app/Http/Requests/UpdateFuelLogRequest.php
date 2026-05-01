<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'log_date'      => 'sometimes|date',
            'unit_code'     => 'nullable|string',
            'unit_type'     => 'nullable|string',
            'hm_km'         => 'nullable|numeric',
            'liter_out'     => 'sometimes|numeric|min:0',
            'stock_in'      => 'nullable|numeric|min:0',
            'operator_name' => 'nullable|string',
            'notes'         => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'log_date.date'     => 'Format tanggal tidak valid.',
            'liter_out.min'     => 'Jumlah liter tidak boleh negatif.',
            'stock_in.min'      => 'Stok masuk tidak boleh negatif.',
        ];
    }
}
