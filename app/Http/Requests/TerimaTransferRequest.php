<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerimaTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_by_name'     => 'required|string|max:255',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.id'           => 'required|exists:delivery_order_items,id',
            'items.*.qty_received' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'received_by_name.required'     => 'Nama penerima wajib diisi.',
            'items.required'                => 'Minimal 1 item harus disertakan.',
            'items.min'                     => 'Minimal 1 item harus disertakan.',
            'items.*.id.required'           => 'ID item wajib ada.',
            'items.*.id.exists'             => 'Item pengiriman tidak ditemukan.',
            'items.*.qty_received.required' => 'Jumlah yang diterima wajib diisi.',
            'items.*.qty_received.min'      => 'Jumlah yang diterima tidak boleh negatif.',
        ];
    }
}
