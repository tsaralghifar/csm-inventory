<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        // Dipakai untuk store (POST) dan update (PUT/PATCH)
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name'        => $isUpdate ? 'sometimes|string' : 'required|string',
            'code'        => $isUpdate
                ? "sometimes|string|unique:categories,code,{$categoryId}"
                : 'required|string|unique:categories',
            'description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'code.required' => 'Kode kategori wajib diisi.',
            'code.unique'   => 'Kode kategori sudah digunakan.',
        ];
    }
}
