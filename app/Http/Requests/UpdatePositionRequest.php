<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('positions.manage') ?? false;
    }

    /**
     * Prepare data before validation.
     */
    protected function prepareForValidation(): void
    {
        $code = $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : null;

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'code' => $code,
            'payroll_type' => in_array($this->input('payroll_type'), ['security', 'regular'], true) ? $this->input('payroll_type') : 'regular',
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $positionId = $this->route('position')?->id ?? $this->route('position');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('positions', 'code')->ignore($positionId)],
            'payroll_type' => ['nullable', 'string', 'in:regular,security'],
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
            'name' => 'Nama Jabatan',
            'code' => 'Kode Jabatan',
            'description' => 'Deskripsi',
            'is_active' => 'Status Aktif',
        ];
    }
}
