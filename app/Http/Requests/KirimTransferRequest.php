<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KirimTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('dispatch-mr');
    }

    public function rules(): array
    {
        return [
            'driver_name'      => 'nullable|string|max:255',
            'vehicle_plate'    => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|exists:material_request_items,id',
            'items.*.qty_sent' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'            => 'Minimal 1 item harus disertakan.',
            'items.min'                 => 'Minimal 1 item harus disertakan.',
            'items.*.id.required'       => 'ID item wajib ada.',
            'items.*.id.exists'         => 'Item tidak ditemukan.',
            'items.*.qty_sent.required' => 'Jumlah yang dikirim wajib diisi.',
            'items.*.qty_sent.min'      => 'Jumlah yang dikirim harus lebih dari 0.',
        ];
    }
}
