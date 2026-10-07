<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_code' => ['required', 'string', 'max:20', 'unique:payroll_periods,period_code', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'approval_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_code.required' => 'Kode periode wajib diisi.',
            'period_code.unique' => 'Kode periode sudah digunakan.',
            'period_code.regex' => 'Kode periode hanya boleh berisi huruf, angka, tanda strip, atau garis bawah.',
            'start_date.required' => 'Tanggal awal periode wajib diisi.',
            'end_date.required' => 'Tanggal akhir periode wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal akhir periode tidak boleh lebih awal dari tanggal awal.',
        ];
    }
}
