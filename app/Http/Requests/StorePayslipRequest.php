<?php

namespace App\Http\Requests;

use App\Models\Payslip;
use Illuminate\Foundation\Http\FormRequest;

class StorePayslipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'employee_id' => [
                'required',
                'exists:employees,id',
                function ($attribute, $value, $fail) {
                    $periodId = $this->input('payroll_period_id');
                    if ($periodId && Payslip::where('payroll_period_id', $periodId)->where('employee_id', $value)->exists()) {
                        $fail('Karyawan yang dipilih sudah memiliki slip gaji pada periode ini.');
                    }
                },
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pay_component_id' => ['nullable', 'exists:pay_components,id'],
            'items.*.item_code_snapshot' => ['nullable', 'string', 'max:50'],
            'items.*.description_snapshot' => ['nullable', 'string', 'max:190'],
            'items.*.item_type' => ['required', 'string', 'in:earning,deduction'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'payroll_period_id.required' => 'Periode gaji wajib dipilih.',
            'payroll_period_id.exists' => 'Periode gaji tidak ditemukan.',
            'employee_id.required' => 'Karyawan penerima gaji wajib dipilih.',
            'employee_id.exists' => 'Karyawan tidak ditemukan dalam sistem.',
            'items.required' => 'Rincian komponen gaji wajib diisi minimal 1 item.',
            'items.min' => 'Rincian komponen gaji wajib memiliki minimal 1 item.',
            'items.*.item_type.required' => 'Tipe item wajib ditentukan (pendapatan atau potongan).',
            'items.*.item_type.in' => 'Tipe item harus earning atau deduction.',
            'items.*.amount.required' => 'Nominal setiap baris komponen wajib diisi.',
            'items.*.amount.min' => 'Nominal komponen tidak boleh negatif.',
            'items.*.amount.numeric' => 'Nominal harus berupa angka yang valid.',
        ];
    }
}
