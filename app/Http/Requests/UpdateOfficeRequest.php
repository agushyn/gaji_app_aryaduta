<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('offices.manage') ?? false;
    }

    /**
     * Prepare data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'code' => strtoupper(trim((string) $this->input('code', ''))),
            'address' => trim((string) $this->input('address', '')),
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
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
        $officeId = $this->route('office')?->id ?? $this->route('office');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('offices', 'code')->ignore($officeId)],
            'address' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Custom attributes for validation errors in Indonesian.
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama Kantor',
            'code' => 'Kode Kantor',
            'address' => 'Alamat Kantor',
            'phone' => 'Nomor Telepon',
            'email' => 'Alamat Email',
            'is_active' => 'Status Aktif',
        ];
    }
}
