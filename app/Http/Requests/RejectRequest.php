<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dipakai untuk endpoint reject di:
 * - MaterialRequestController::reject()
 * - PermintaanMaterialController::reject()
 */
class RejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:5',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min'      => 'Alasan penolakan minimal 5 karakter.',
        ];
    }
}
