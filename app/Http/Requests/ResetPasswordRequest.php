<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Password baru wajib diisi.',
            'password.min'      => 'Password minimal 8 karakter.',
        ];
    }
}
