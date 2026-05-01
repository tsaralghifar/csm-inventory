<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveSuratJalanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_by' => 'required|string|max:255',
            'notes'       => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'received_by.required' => 'Nama penerima wajib diisi.',
        ];
    }
}
