<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveMaterialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                => 'required|array',
            'items.*.id'           => 'required|exists:material_request_items,id',
            'items.*.qty_approved' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                => 'Data item wajib disertakan.',
            'items.*.id.required'           => 'ID item wajib ada.',
            'items.*.id.exists'             => 'Item tidak ditemukan.',
            'items.*.qty_approved.required' => 'Jumlah yang disetujui wajib diisi.',
            'items.*.qty_approved.min'      => 'Jumlah yang disetujui tidak boleh negatif.',
        ];
    }
}
