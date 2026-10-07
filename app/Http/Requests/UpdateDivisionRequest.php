<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('divisions.manage') ?? false;
    }

    /**
     * Prepare data before validation.
     */
    protected function prepareForValidation(): void
    {
        $division = $this->route('division');
        $defaultActive = $division ? (bool) $division->is_active : true;
        $code = $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : ($division?->code ?? null);

        $officeId = $this->input('office_id', $division?->office_id);
        if (empty($officeId)) {
            $defaultOffice = \App\Models\Office::firstOrCreate(
                ['code' => 'BP'],
                [
                    'name' => 'CV. BINA PUSTAKA',
                    'address' => 'Jl. M. Nasir, Bendungan Kec. Cilodong - Kel. Cilodong, Depok.',
                    'is_active' => true,
                ]
            );
            $officeId = $defaultOffice->id;
        }

        $this->merge([
            'office_id' => $officeId,
            'department_id' => $this->input('department_id', $division?->department_id),
            'name' => trim((string) $this->input('name', '')),
            'code' => $code,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
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
        return [
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
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
            'office_id' => 'Kantor',
            'department_id' => 'Departemen',
            'name' => 'Nama Divisi',
            'code' => 'Kode Divisi',
            'description' => 'Deskripsi',
            'is_active' => 'Status Aktif',
        ];
    }
}
