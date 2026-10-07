<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $component = $this->route('pay_component');
        $componentId = is_object($component) ? $component->id : $component;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pay_components', 'code')->ignore($componentId),
                'regex:/^[A-Za-z0-9_]+$/',
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'in:earning,deduction'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode komponen wajib diisi.',
            'code.unique' => 'Kode komponen sudah terdaftar.',
            'code.regex' => 'Kode komponen hanya boleh berisi huruf, angka, dan garis bawah (_).',
            'name.required' => 'Nama komponen gaji wajib diisi.',
            'type.required' => 'Tipe komponen wajib dipilih.',
            'type.in' => 'Tipe komponen harus pendapatan (earning) atau potongan (deduction).',
            'sort_order.min' => 'Urutan komponen tidak boleh bernilai negatif.',
        ];
    }
}
