<?php

namespace App\Http\Requests;

use App\Services\FormulaEvaluatorService;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollCalculationConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $configParam = $this->route('payroll_configuration')
            ?? $this->route('payroll_calculation_config')
            ?? $this->route('config')
            ?? $this->route('id');

        $configId = is_object($configParam) ? ($configParam->id ?? null) : $configParam;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'unique:payroll_calculation_configs,code,' . $configId],
            'component_id' => ['required', 'exists:pay_components,id'],
            'day_type' => ['required', 'string', 'in:working_day,holiday,national_holiday,joint_leave,weekend,all'],
            'is_active' => ['boolean'],
            'formula' => ['required', 'array'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $formula = $this->input('formula');
            if (is_array($formula)) {
                $evaluator = app(FormulaEvaluatorService::class);
                $validation = $evaluator->validateFormula($formula);
                if (is_array($validation) && ! ($validation['valid'] ?? true)) {
                    foreach ($validation['errors'] ?? [] as $error) {
                        $validator->errors()->add('formula', $error);
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama konfigurasi perhitungan gaji wajib diisi.',
            'component_id.required' => 'Komponen gaji wajib dipilih.',
            'component_id.exists' => 'Komponen gaji yang dipilih tidak valid.',
            'day_type.required' => 'Jenis hari wajib dipilih.',
            'day_type.in' => 'Jenis hari tidak valid.',
            'formula.required' => 'Struktur formula perhitungan wajib disediakan.',
            'formula.array' => 'Formula harus berupa format struktur AST yang valid.',
        ];
    }
}
