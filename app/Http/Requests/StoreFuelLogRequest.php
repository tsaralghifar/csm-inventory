<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'log_date'      => 'required|date',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'unit_id'       => 'nullable|exists:units,id',
            'unit_code'     => 'nullable|string',
            'unit_type'     => 'nullable|string',
            'division'      => 'nullable|string',
            'hm_km'         => 'nullable|numeric',
            'fill_time'     => 'nullable|date_format:H:i',
            'liter_out'     => 'required|numeric|min:0',
            'stock_in'      => 'nullable|numeric|min:0',
            'operator_name' => 'nullable|string',
            'notes'         => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'log_date.required'     => 'Tanggal wajib diisi.',
            'log_date.date'         => 'Format tanggal tidak valid.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'liter_out.required'    => 'Jumlah liter keluar wajib diisi.',
            'liter_out.min'         => 'Jumlah liter tidak boleh negatif.',
            'fill_time.date_format' => 'Format waktu harus HH:MM (contoh: 08:30).',
        ];
    }
}
