<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchMaterialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dispatch-mr');
    }

    public function rules(): array
    {
        return [
            'driver_name'   => 'nullable|string',
            'vehicle_plate' => 'nullable|string',
            'notes'         => 'nullable|string',
            'items'         => 'nullable|array',
        ];
    }
}
