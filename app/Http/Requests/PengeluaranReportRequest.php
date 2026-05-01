<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dipakai untuk ReportController::pengeluaranReport()
 * Method stockReport() hanya pakai nullable filter, tidak butuh Form Request tersendiri.
 */
class PengeluaranReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required'    => 'Gudang wajib dipilih.',
            'warehouse_id.exists'      => 'Gudang tidak ditemukan.',
            'date_from.required'       => 'Tanggal mulai wajib diisi.',
            'date_from.date'           => 'Format tanggal mulai tidak valid.',
            'date_to.required'         => 'Tanggal akhir wajib diisi.',
            'date_to.date'             => 'Format tanggal akhir tidak valid.',
            'date_to.after_or_equal'   => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ];
    }
}
