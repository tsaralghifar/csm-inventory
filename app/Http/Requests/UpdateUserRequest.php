<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'         => 'sometimes|string|max:255',
            'email'        => "sometimes|email|unique:users,email,{$userId}",
            'phone'        => 'nullable|string|max:20',
            'position'     => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active'    => 'sometimes|boolean',
            'role'         => 'sometimes|exists:roles,name,guard_name,web',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'   => 'Format email tidak valid.',
            'email.unique'  => 'Email sudah digunakan oleh user lain.',
            'role.exists'   => 'Role tidak ditemukan.',
        ];
    }
}
