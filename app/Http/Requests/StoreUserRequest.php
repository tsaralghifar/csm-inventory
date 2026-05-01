<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password'     => 'required|string|min:8',
            'phone'        => 'nullable|string|max:20',
            'employee_id'  => 'nullable|string|unique:users',
            'position'     => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'role'         => 'required|exists:roles,name,guard_name,web',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama wajib diisi.',
            'email.required'     => 'Email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
            'email.unique'       => 'Email sudah digunakan.',
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 8 karakter.',
            'employee_id.unique' => 'ID karyawan sudah digunakan.',
            'role.required'      => 'Role wajib dipilih.',
            'role.exists'        => 'Role tidak ditemukan.',
        ];
    }
}
