<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-warehouses');
    }

    public function rules(): array
    {
        return [
            'name'      => 'sometimes|string|max:255',
            'location'  => 'nullable|string',
            'address'   => 'nullable|string',
            'pic_name'  => 'nullable|string',
            'pic_phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'notes'     => 'nullable|string',
        ];
    }
}
