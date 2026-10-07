<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'in:national_holiday,joint_leave,custom'],
            'is_joint_leave' => ['boolean'],
            'is_payroll_holiday' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal hari libur wajib diisi.',
            'date.date' => 'Format tanggal tidak valid.',
            'name.required' => 'Nama hari libur wajib diisi.',
            'type.required' => 'Jenis hari libur wajib dipilih.',
            'type.in' => 'Jenis hari libur tidak valid.',
        ];
    }
}
