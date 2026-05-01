<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-roles');
    }

    public function rules(): array
    {
        return [
            'role'          => 'required|exists:roles,name,guard_name,web',
            'permissions'   => 'present|array',
            'permissions.*' => 'exists:permissions,name,guard_name,web',
        ];
    }

    public function messages(): array
    {
        return [
            'role.required'        => 'Role wajib dipilih.',
            'role.exists'          => 'Role tidak ditemukan.',
            'permissions.present'  => 'Field permissions harus disertakan (boleh array kosong).',
            'permissions.*.exists' => 'Salah satu permission tidak ditemukan.',
        ];
    }
}
