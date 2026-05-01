<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dipakai untuk endpoint preview saldo awal.
 */
class PreviewSaldoAwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-items');
    }

    public function rules(): array
    {
        return [
            'file'         => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'warehouse_id' => 'required|exists:warehouses,id',
            'sheet_name'   => 'nullable|string',
            'auto_create'  => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'         => 'File wajib diupload.',
            'file.mimes'            => 'File harus berformat xlsx, xls, atau csv.',
            'file.max'              => 'Ukuran file maksimal 20MB.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists'   => 'Gudang tidak ditemukan.',
        ];
    }
}
