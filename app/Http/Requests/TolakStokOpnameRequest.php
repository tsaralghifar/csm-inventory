<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TolakStokOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperuser() || $this->user()->isAdminHO();
    }

    public function rules(): array
    {
        return [
            'alasan_penolakan' => 'required|string|min:5',
        ];
    }

    public function messages(): array
    {
        return [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi.',
            'alasan_penolakan.min'      => 'Alasan penolakan minimal 5 karakter.',
        ];
    }
}
