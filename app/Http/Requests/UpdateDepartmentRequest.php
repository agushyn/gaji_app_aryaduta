<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('departments.manage') ?? false;
    }

    /**
     * Prepare data before validation.
     */
    protected function prepareForValidation(): void
    {
        $department = $this->route('department');
        $defaultActive = $department ? (bool) $department->is_active : true;

        $this->merge([
            'name' => trim($this->input('name', '')),
            'code' => strtoupper(trim($this->input('code', ''))),
            'description' => $this->filled('description') ? trim($this->input('description')) : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : $defaultActive,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $departmentId = $this->route('department')?->id ?? $this->route('department');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($departmentId)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Custom attributes for validation errors in Indonesian.
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama Departemen',
            'code' => 'Kode Departemen',
            'description' => 'Deskripsi',
            'is_active' => 'Status Aktif',
        ];
    }
}
