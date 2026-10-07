<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayslipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
