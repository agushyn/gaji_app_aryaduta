<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Models\Division;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    /**
     * Prepare data before validation.
     */
    protected function prepareForValidation(): void
    {
        $employee = $this->route('employee');

        // Resolve office_id if not provided
        $officeId = $this->office_id;
        if (empty($officeId)) {
            if (! empty($this->division_id)) {
                $div = Division::find($this->division_id);
                $officeId = $div?->office_id;
            } elseif ($employee && $employee->office_id) {
                $officeId = $employee->office_id;
            } else {
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
            $this->merge(['office_id' => $officeId]);
        }

        // If division_id is present but has no office_id yet (legacy tests), assign it
        if (! empty($this->division_id)) {
            $div = Division::find($this->division_id);
            if ($div && empty($div->office_id)) {
                $div->update(['office_id' => $officeId]);
            }
        }

        // Backward compatibility fallback for tests providing legacy department string or keeping existing
        if (empty($this->division_id)) {
            if (! empty($this->department)) {
                $dept = Department::firstOrCreate(
                    ['name' => trim($this->department)],
                    [
                        'code' => strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $this->department), 0, 10)) ?: 'GEN',
                        'is_active' => true,
                    ]
                );
                $div = Division::firstOrCreate(
                    ['office_id' => $this->office_id, 'name' => trim($this->department)],
                    [
                        'department_id' => $dept->id,
                        'code' => ($dept->code ?: 'GEN').'-DIV',
                        'is_active' => true,
                    ]
                );
                $this->merge([
                    'department_id' => $dept->id,
                    'division_id' => $div->id,
                ]);
            } elseif ($employee && $employee->division_id) {
                $this->merge([
                    'department_id' => $employee->department_id,
                    'division_id' => $employee->division_id,
                ]);
            }
        }

        // Sync department name from division, and department_id only if not already provided
        if (! empty($this->division_id)) {
            $div = Division::find($this->division_id);
            if ($div) {
                $merged = ['department' => $div->name];
                if (empty($this->department_id) && ! empty($div->department_id)) {
                    $merged['department_id'] = $div->department_id;
                }
                $this->merge($merged);
            }
        }

        // Resolve position_id or position string
        if (empty($this->position_id) && ! empty($this->position)) {
            $pos = \App\Models\Position::firstOrCreate(
                ['name' => trim($this->position)],
                ['is_active' => true]
            );
            $this->merge(['position_id' => $pos->id]);
        } elseif (! empty($this->position_id)) {
            $pos = \App\Models\Position::find($this->position_id);
            if ($pos) {
                $this->merge(['position' => $pos->name]);
            }
        } elseif ($employee && $employee->position_id) {
            $this->merge(['position_id' => $employee->position_id]);
        }

        if ($this->has('bpjs_ketenagakerjaan_enabled')) {
            $enabled = $this->boolean('bpjs_ketenagakerjaan_enabled');
            $this->merge([
                'bpjs_ketenagakerjaan_enabled' => $enabled,
                'bpjs_ketenagakerjaan_amount' => $enabled ? ($this->input('bpjs_ketenagakerjaan_amount') ?? 0) : 0,
            ]);
        }

        if ($this->has('default_overtime_rate')) {
            $this->merge([
                'default_overtime_rate' => $this->input('default_overtime_rate') ?? 0,
            ]);
        }

        if ($this->has('payroll_type')) {
            $this->merge([
                'payroll_type' => $this->filled('payroll_type') ? $this->input('payroll_type') : null,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')->ignore($employee),
            ],
            'full_name' => ['required', 'string', 'max:190'],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where(function ($query) {
                    if (! empty($this->office_id)) {
                        $query->where('office_id', $this->office_id);
                    }
                    if (! empty($this->department_id)) {
                        $query->where('department_id', $this->department_id);
                    }
                }),
            ],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'payroll_type' => ['nullable', 'string', 'in:regular,security'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'department' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                function ($attribute, $value, $fail) {
                    if (! empty($value) && ! PhoneNumberNormalizer::isValid($value)) {
                        $fail('Nomor WhatsApp tidak valid. Masukkan nomor seluler yang benar (contoh: 08123456789 atau +628123456789).');
                    }
                },
            ],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['required', 'string', 'in:active,inactive'],
            'gaji_pokok' => ['nullable', 'numeric', 'min:0'],
            'tunjangan_kehadiran' => ['nullable', 'numeric', 'min:0'],
            'tunjangan_keluarga' => ['nullable', 'numeric', 'min:0'],
            'tunjangan_jabatan' => ['nullable', 'numeric', 'min:0'],
            'uang_makan_per_hari' => ['nullable', 'numeric', 'min:0'],
            'uang_transportasi_per_hari' => ['nullable', 'numeric', 'min:0'],
            'bpjs_ketenagakerjaan_enabled' => ['nullable', 'boolean'],
            'bpjs_ketenagakerjaan_amount' => ['nullable', 'numeric', 'min:0'],
            'default_overtime_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Custom messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'division_id.exists' => 'Divisi yang dipilih tidak valid atau bukan milik kantor yang dipilih.',
        ];
    }

    /**
     * Custom attribute names for validation error messages in Indonesian.
     */
    public function attributes(): array
    {
        return [
            'employee_number' => 'Nomor Induk Karyawan (NIK)',
            'full_name' => 'Nama Lengkap',
            'office_id' => 'Kantor',
            'division_id' => 'Divisi',
            'position_id' => 'Jabatan',
            'department_id' => 'Departemen',
            'department' => 'Departemen',
            'position' => 'Jabatan',
            'email' => 'Alamat Email',
            'phone' => 'Nomor WhatsApp',
            'hire_date' => 'Tanggal Bergabung',
            'employment_status' => 'Status Kepegawaian',
            'gaji_pokok' => 'Gaji Pokok',
            'tunjangan_kehadiran' => 'Tunjangan Kehadiran',
            'tunjangan_keluarga' => 'Tunjangan Keluarga',
            'tunjangan_jabatan' => 'Tunjangan Jabatan',
            'uang_makan_per_hari' => 'Uang Makan per Hari',
            'uang_transportasi_per_hari' => 'Uang Transportasi per Hari',
            'bpjs_ketenagakerjaan_amount' => 'Potongan BPJS Ketenagakerjaan',
            'default_overtime_rate' => 'Upah Lembur Default',
        ];
    }
}
