<?php

namespace App\Http\Requests;

use App\Models\OvertimeEntry;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdateOvertimeEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var PayrollPeriod|null $period */
        $period = $this->route('payrollPeriod');

        if (! $period) {
            return false;
        }

        return $this->user()?->can('update', $period) && $period->canBeModified();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PayrollPeriod $period */
        $period = $this->route('payrollPeriod');
        $startDate = $period?->start_date?->toDateString();
        $endDate = $period?->end_date?->toDateString();

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(function ($query) {
                    $query->where('employment_status', 'active');
                }),
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
                "after_or_equal:{$startDate}",
                "before_or_equal:{$endDate}",
            ],
            'day_type' => ['required', 'string', 'in:working_day,holiday'],
            'start_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'end_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Custom validation logic.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var PayrollPeriod|null $period */
            $period = $this->route('payrollPeriod');
            if (! $period || ! $period->canBeModified()) {
                $v->errors()->add('period', 'Periode gaji tidak dalam status yang dapat diubah.');
                return;
            }

            /** @var OvertimeEntry|null $entry */
            $entry = $this->route('overtimeEntry');

            $startTime = (string) $this->input('start_time');
            $endTime = (string) $this->input('end_time');

            try {
                $duration = OvertimeEntry::calculateDuration($startTime, $endTime);
                if ($duration['minutes'] <= 0) {
                    $v->errors()->add('end_time', 'Durasi lembur harus lebih dari 0 menit.');
                }
            } catch (InvalidArgumentException $e) {
                $v->errors()->add('end_time', $e->getMessage());
            }

            // Check duplicate entry excluding current entry ID
            if ($period && $this->filled('employee_id') && $this->filled('date') && $this->filled('start_time') && $this->filled('end_time')) {
                $exists = OvertimeEntry::where('payroll_period_id', $period->id)
                    ->where('employee_id', $this->input('employee_id'))
                    ->where('date', $this->input('date'))
                    ->where('start_time', $startTime)
                    ->where('end_time', $endTime)
                    ->when($entry, fn ($q) => $q->where('id', '!=', $entry->id))
                    ->exists();

                if ($exists) {
                    $v->errors()->add('start_time', 'Entri lembur untuk karyawan, tanggal, dan rentang jam yang sama sudah ada.');
                }
            }
        });
    }

    /**
     * Custom error messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var PayrollPeriod|null $period */
        $period = $this->route('payrollPeriod');
        $startStr = $period?->start_date?->format('d/m/Y') ?? '';
        $endStr = $period?->end_date?->format('d/m/Y') ?? '';

        return [
            'employee_id.required' => 'Pilih karyawan yang melakukan lembur.',
            'employee_id.exists' => 'Karyawan yang dipilih tidak valid atau tidak berstatus aktif.',
            'date.required' => 'Tanggal lembur wajib diisi.',
            'date.date_format' => 'Format tanggal lembur harus YYYY-MM-DD.',
            'date.after_or_equal' => "Tanggal lembur tidak boleh sebelum awal periode ({$startStr}).",
            'date.before_or_equal' => "Tanggal lembur tidak boleh melebihi akhir periode ({$endStr}).",
            'day_type.required' => 'Pilih jenis hari lembur (Hari Biasa atau Hari Libur).',
            'day_type.in' => 'Jenis hari harus bernilai Hari Biasa atau Hari Libur.',
            'start_time.required' => 'Jam mulai lembur wajib diisi.',
            'start_time.regex' => 'Format jam mulai lembur harus JJ:MM (contoh: 17:00).',
            'end_time.required' => 'Jam selesai lembur wajib diisi.',
            'end_time.regex' => 'Format jam selesai lembur harus JJ:MM (contoh: 20:00).',
            'note.max' => 'Keterangan lembur maksimal 500 karakter.',
        ];
    }
}
