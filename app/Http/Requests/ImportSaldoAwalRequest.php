<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dipakai untuk endpoint import saldo awal.
 */
class ImportSaldoAwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-items');
    }

    public function rules(): array
    {
        return [
            'file'          => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'sheet_name'    => 'nullable|string',
            'tanggal_saldo' => 'required|date',
            'overwrite'     => 'boolean',
            'auto_create'   => 'boolean',
            'category_id'   => 'required_if:auto_create,true|nullable|exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'              => 'File wajib diupload.',
            'file.mimes'                 => 'File harus berformat xlsx, xls, atau csv.',
            'file.max'                   => 'Ukuran file maksimal 20MB.',
            'warehouse_id.required'      => 'Gudang wajib dipilih.',
            'tanggal_saldo.required'     => 'Tanggal saldo wajib diisi.',
            'tanggal_saldo.date'         => 'Format tanggal tidak valid.',
            'category_id.required_if'    => 'Kategori wajib dipilih jika auto-create barang baru diaktifkan.',
        ];
    }
}
