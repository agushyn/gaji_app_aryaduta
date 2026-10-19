<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollCalculationConfig;
use Carbon\Carbon;
use InvalidArgumentException;

class OvertimeCalculationService
{
    public function __construct(
        protected FormulaEvaluatorService $formulaEvaluator,
        protected PayrollDayTypeService $dayTypeService
    ) {}

    /**
     * Default AST for working day overtime (Lembur Hari Biasa).
     */
    public function getDefaultWorkingDayFormula(): array
    {
        return [
            'type' => 'add',
            'break_threshold' => 3.0,
            'break_duration_per_day' => 0.5,
            'operands' => [
                [
                    'name' => 'first_hours_pay',
                    'label' => 'Jam Pertama',
                    'type' => 'multiply',
                    'operands' => [
                        ['variable' => 'employee_overtime_rate'],
                        ['variable' => 'overtime_days'],
                        ['variable' => 'first_hour_multiplier', 'default' => 1.5],
                    ],
                ],
                [
                    'name' => 'subsequent_hours_pay',
                    'label' => 'Jam Berikutnya',
                    'type' => 'multiply',
                    'operands' => [
                        ['variable' => 'subsequent_overtime_hours'],
                        ['variable' => 'employee_overtime_rate'],
                        ['variable' => 'subsequent_hour_multiplier', 'default' => 2.0],
                    ],
                ],
            ],
        ];
    }

    /**
     * Default AST for holiday overtime (Lembur Hari Libur).
     */
    public function getDefaultHolidayFormula(): array
    {
        return [
            'name' => 'holiday_pay',
            'label' => 'Lembur Hari Libur',
            'type' => 'multiply',
            'break_threshold' => 4.0,
            'break_duration_per_day' => 1.0,
            'operands' => [
                ['variable' => 'total_overtime_hours'],
                ['variable' => 'employee_overtime_rate'],
                ['variable' => 'holiday_overtime_multiplier', 'default' => 2.0],
            ],
        ];
    }

    /**
     * Parse duration in various formats (decimal float, integer minutes, or "HH:MM" string)
     * into normalized decimal hours and total minutes.
     *
     * @param  mixed  $duration
     * @return array{hours: float, minutes: int, formatted: string}
     */
    public function parseDuration(mixed $duration): array
    {
        if ($duration === null || $duration === '') {
            return ['hours' => 0.0, 'minutes' => 0, 'formatted' => '0:00'];
        }

        if (is_string($duration)) {
            $trimmed = trim($duration);
            // Check HH:MM or HH:MM:SS format
            if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $trimmed, $matches)) {
                $h = (int) $matches[1];
                $m = (int) $matches[2];
                $totalMinutes = ($h * 60) + $m;
                $decimalHours = round($totalMinutes / 60, 4);

                return [
                    'hours' => $decimalHours,
                    'minutes' => $totalMinutes,
                    'formatted' => sprintf('%d:%02d', $h, $m),
                ];
            }

            if (is_numeric($trimmed)) {
                $duration = (float) $trimmed;
            }
        }

        if (is_numeric($duration)) {
            $hours = (float) $duration;
            if ($hours < 0) {
                throw new InvalidArgumentException('Jam lembur tidak boleh bernilai negatif.');
            }
            $totalMinutes = (int) round($hours * 60);

            $wholeHours = (int) floor($hours);
            $remMinutes = (int) round(($hours - $wholeHours) * 60);
            if ($remMinutes >= 60) {
                $wholeHours += 1;
                $remMinutes = 0;
            }

            return [
                'hours' => round($hours, 4),
                'minutes' => $totalMinutes,
                'formatted' => sprintf('%d:%02d', $wholeHours, $remMinutes),
            ];
        }

        throw new InvalidArgumentException("Format durasi lembur tidak valid: '{$duration}'.");
    }

    /**
     * Calculate break time deduction for overtime hours.
     *
     * Rules:
     * - Working Day: Jika overtime > 3.0 jam (> 180 menit), potongan = Jumlah Hari Lembur × 0.5 jam.
     * - Holiday:     Jika overtime > 4.0 jam (> 240 menit), potongan = Jumlah Hari Lembur × 1.0 jam.
     * - Jam lembur bersih = max(Total Jam Lembur - Potongan, 0).
     *
     * @param  mixed  $totalHours Total gross overtime hours (float, int, or "HH:MM" string)
     * @param  float  $days Number of overtime days
     * @param  string  $dayType 'working_day' or 'holiday'
     * @param  float|null  $customThreshold Custom threshold hours (default: 3 for working day, 4 for holiday)
     * @param  float|null  $customDurationPerDay Custom deduction per day (default: 0.5 for working day, 1.0 for holiday)
     * @return array{
     *     gross_hours: float,
     *     gross_minutes: int,
     *     days: float,
     *     threshold: float,
     *     threshold_minutes: int,
     *     duration_per_day: float,
     *     deduction: float,
     *     net_hours: float,
     *     net_minutes: int,
     *     is_deducted: bool,
     *     formatted_gross: string
     * }
     */
    public function calculateBreakDeduction(
        mixed $totalHours,
        float $days,
        string $dayType = 'working_day',
        ?float $customThreshold = null,
        ?float $customDurationPerDay = null
    ): array {
        $parsed = $this->parseDuration($totalHours);
        $decimalHours = $parsed['hours'];
        $overtimeMinutes = $parsed['minutes'];

        if ($decimalHours < 0 || $days < 0) {
            throw new InvalidArgumentException('Jam lembur atau jumlah hari tidak boleh bernilai negatif.');
        }

        $isHoliday = in_array($dayType, ['holiday', 'national_holiday', 'joint_leave', 'weekend'], true);

        $defaultThreshold = $isHoliday ? 4.0 : 3.0;
        $defaultDuration = $isHoliday ? 1.0 : 0.5;

        $threshold = $customThreshold !== null ? (float) $customThreshold : $defaultThreshold;
        $durationPerDay = $customDurationPerDay !== null ? (float) $customDurationPerDay : $defaultDuration;

        // Threshold in minutes to ensure absolute exactness without early rounding
        // Hari biasa: 3.0 jam = 180 menit.
        // Hari libur: 4.0 jam = 240 menit.
        $thresholdMinutes = (int) round($threshold * 60);

        // Aturan: Potongan mulai berlaku jika overtime >= threshold (>= 180 menit untuk hari kerja, >= 240 menit untuk hari libur)
        // dan jumlah hari > 0
        $isDeducted = ($days > 0) && ($overtimeMinutes >= $thresholdMinutes);

        if ($isDeducted) {
            $deduction = round($days * $durationPerDay, 4);
            $netHours = max(round($decimalHours - $deduction, 4), 0.0);
        } else {
            $deduction = 0.0;
            $netHours = $decimalHours;
        }

        $netMinutes = (int) round($netHours * 60);

        return [
            'gross_hours' => $decimalHours,
            'gross_minutes' => $overtimeMinutes,
            'days' => round($days, 4),
            'threshold' => $threshold,
            'threshold_minutes' => $thresholdMinutes,
            'duration_per_day' => $durationPerDay,
            'deduction' => $deduction,
            'net_hours' => $netHours,
            'net_minutes' => $netMinutes,
            'is_deducted' => $isDeducted,
            'formatted_gross' => $parsed['formatted'],
        ];
    }

    public function calculateForEmployee(Employee $employee, array $params = []): array
    {
        return $this->calculateOvertime($employee, $params);
    }

    /**
     * Calculate overtime for an employee or given parameters.
     *
     * @param  array{
     *     rate?: float|string|null,
     *     working_day_hours?: float|int|string|null,
     *     working_day_minutes?: int|null,
     *     working_day_days?: float|int|null,
     *     holiday_hours?: float|int|string|null,
     *     holiday_minutes?: int|null,
     *     holiday_days?: float|int|null,
     *     working_break_threshold?: float|int|null,
     *     working_break_duration_per_day?: float|int|null,
     *     holiday_break_threshold?: float|int|null,
     *     holiday_break_duration_per_day?: float|int|null,
     *     daily_records?: array<int, array{date: string, hours?: float|int|string, minutes?: int}>,
     *     config_id?: int|null
     * }  $params
     * @return array{
     *     amount: string,
     *     rate: string,
     *     quantity: string,
     *     gross_quantity: string,
     *     break_deduction: string,
     *     snapshot: array<string, mixed>,
     *     trace: array<int, string>
     * }
     */
    public function calculateOvertime(?Employee $employee, array $params = []): array
    {
        // 1. Resolve employee overtime rate
        $defaultRate = $employee ? (float) ($employee->default_overtime_rate ?? 0) : 0.0;
        $rate = isset($params['rate']) && $params['rate'] !== ''
            ? (float) $params['rate']
            : $defaultRate;

        if ($rate < 0) {
            throw new InvalidArgumentException('Tarif lembur tidak boleh bernilai negatif.');
        }

        // 2. Parse daily records if provided, or use summary hours/minutes
        $workingDayRaw = $params['working_day_hours'] ?? $params['working_day_minutes'] ?? $params['working_hours'] ?? 0;
        $holidayRaw = $params['holiday_hours'] ?? $params['holiday_minutes'] ?? 0;
        $workingDayDays = (float) ($params['working_day_days'] ?? $params['days'] ?? 0);
        $holidayDays = (float) ($params['holiday_days'] ?? 0);

        if (isset($params['working_day_minutes']) && ! isset($params['working_day_hours'])) {
            $workingDayRaw = ((float) $params['working_day_minutes']) / 60;
        }
        if (isset($params['holiday_minutes']) && ! isset($params['holiday_hours'])) {
            $holidayRaw = ((float) $params['holiday_minutes']) / 60;
        }

        $parsedWd = $this->parseDuration($workingDayRaw);
        $parsedHl = $this->parseDuration($holidayRaw);

        $workingDayGrossHours = $parsedWd['hours'];
        $holidayGrossHours = $parsedHl['hours'];

        if (! empty($params['daily_records'])) {
            $workingDayGrossHours = 0.0;
            $workingDayDays = 0;
            $holidayGrossHours = 0.0;
            $holidayDays = 0;

            foreach ($params['daily_records'] as $rec) {
                $rawRec = $rec['hours'] ?? $rec['minutes'] ?? 0;
                if (isset($rec['minutes']) && ! isset($rec['hours'])) {
                    $rawRec = ((float) $rec['minutes']) / 60;
                }
                $parsedRec = $this->parseDuration($rawRec);
                $recHours = $parsedRec['hours'];

                if ($recHours < 0) {
                    throw new InvalidArgumentException('Jam lembur harian tidak boleh bernilai negatif.');
                }
                if ($recHours == 0.0) continue;

                $date = $rec['date'] ?? now()->toDateString();
                $isHoliday = $this->dayTypeService->isOvertimeHoliday($date);

                if ($isHoliday) {
                    $holidayGrossHours += $recHours;
                    $holidayDays += 1;
                } else {
                    $workingDayGrossHours += $recHours;
                    $workingDayDays += 1;
                }
            }
        } else {
            // Default 1 day if hours provided without explicit days
            if ($workingDayDays <= 0 && $workingDayGrossHours > 0) {
                $workingDayDays = 1;
            }
            if ($holidayDays <= 0 && $holidayGrossHours > 0) {
                $holidayDays = 1;
            }
        }

        if ($workingDayGrossHours < 0 || $holidayGrossHours < 0 || $workingDayDays < 0 || $holidayDays < 0) {
            throw new InvalidArgumentException('Jam lembur atau jumlah hari tidak boleh bernilai negatif.');
        }

        $allTraces = [];
        $snapshot = [
            'employee_id' => $employee?->id,
            'employee_name' => $employee?->full_name,
            'employee_overtime_rate' => $rate,
        ];

        // 3. Resolve active configurations
        $workingConfig = PayrollCalculationConfig::with('currentVersionRecord')
            ->active()
            ->forDayType('working_day')
            ->first();

        $holidayConfig = PayrollCalculationConfig::with('currentVersionRecord')
            ->active()
            ->forDayType('holiday')
            ->first();

        $workingFormula = (is_array($workingConfig?->currentVersionRecord?->formula) && ! empty($workingConfig->currentVersionRecord->formula))
            ? $workingConfig->currentVersionRecord->formula
            : $this->getDefaultWorkingDayFormula();

        $holidayFormula = (is_array($holidayConfig?->currentVersionRecord?->formula) && ! empty($holidayConfig->currentVersionRecord->formula))
            ? $holidayConfig->currentVersionRecord->formula
            : $this->getDefaultHolidayFormula();

        // Configurable break parameters from active config or params
        $workingThreshold = isset($params['working_break_threshold'])
            ? (float) $params['working_break_threshold']
            : (float) ($workingFormula['break_threshold'] ?? 3.0);

        $workingDurationPerDay = isset($params['working_break_duration_per_day'])
            ? (float) $params['working_break_duration_per_day']
            : (float) ($workingFormula['break_duration_per_day'] ?? 0.5);

        $holidayThreshold = isset($params['holiday_break_threshold'])
            ? (float) $params['holiday_break_threshold']
            : (float) ($holidayFormula['break_threshold'] ?? 4.0);

        $holidayDurationPerDay = isset($params['holiday_break_duration_per_day'])
            ? (float) $params['holiday_break_duration_per_day']
            : (float) ($holidayFormula['break_duration_per_day'] ?? 1.0);

        // 4. Calculate Break Deductions
        $workingBreak = $this->calculateBreakDeduction(
            $workingDayGrossHours,
            $workingDayDays,
            'working_day',
            $workingThreshold,
            $workingDurationPerDay
        );

        $holidayBreak = $this->calculateBreakDeduction(
            $holidayGrossHours,
            $holidayDays,
            'holiday',
            $holidayThreshold,
            $holidayDurationPerDay
        );

        $workingDayNetHours = $workingBreak['net_hours'];
        $holidayNetHours = $holidayBreak['net_hours'];

        // 5. Calculate Working Day Overtime using Jam Lembur Bersih
        $workingResult = '0.00';
        $workingEval = null;

        if ($workingDayNetHours > 0 && $rate > 0) {
            $workingContext = [
                'employee_overtime_rate' => $rate,
                'overtime_days' => $workingDayDays,
                'total_overtime_hours' => $workingDayNetHours,
            ];

            $workingEval = $this->formulaEvaluator->evaluate($workingFormula, $workingContext);
            $workingResult = $workingEval['result'];
        }

        if ($workingDayGrossHours > 0) {
            $allTraces[] = 'Perhitungan Lembur Hari Biasa:';
            $allTraces[] = "  • Total Jam Lembur: {$workingDayGrossHours} jam ({$workingBreak['formatted_gross']})";
            $allTraces[] = "  • Jumlah Hari Lembur: {$workingDayDays} hari";
            $allTraces[] = '  • Day Type: Hari Kerja';
            $allTraces[] = "  • Batas Potongan: {$workingThreshold} jam ({$workingBreak['threshold_minutes']} menit)";
            $allTraces[] = "  • Potongan Istirahat: {$workingBreak['deduction']} jam" . ($workingBreak['is_deducted'] ? " ({$workingDayDays} hari × {$workingDurationPerDay} jam)" : ' (< batas potongan)');
            $allTraces[] = "  • Jam Lembur Bersih: {$workingDayNetHours} jam";
            if ($workingEval) {
                foreach ($workingEval['trace'] as $t) {
                    $allTraces[] = "  • {$t}";
                }
            }
        }

        $snapshot['working_day'] = [
            'config_id' => $workingConfig?->id,
            'config_version' => $workingConfig?->current_version ?? 1,
            'gross_hours' => $workingDayGrossHours,
            'days' => $workingDayDays,
            'break_threshold' => $workingThreshold,
            'break_duration_per_day' => $workingDurationPerDay,
            'break_deduction' => $workingBreak['deduction'],
            'net_hours' => $workingDayNetHours,
            'hours' => $workingDayNetHours,
            'formula_snapshot' => $workingFormula,
            'result' => $workingResult,
            'trace' => $workingEval['trace'] ?? [],
        ];

        // 6. Calculate Holiday Overtime using Jam Lembur Bersih
        $holidayResult = '0.00';
        $holidayEval = null;

        if ($holidayNetHours > 0 && $rate > 0) {
            $holidayContext = [
                'employee_overtime_rate' => $rate,
                'total_overtime_hours' => $holidayNetHours,
            ];

            $holidayEval = $this->formulaEvaluator->evaluate($holidayFormula, $holidayContext);
            $holidayResult = $holidayEval['result'];
        }

        if ($holidayGrossHours > 0) {
            $allTraces[] = 'Perhitungan Lembur Hari Libur:';
            $allTraces[] = "  • Total Jam Lembur: {$holidayGrossHours} jam ({$holidayBreak['formatted_gross']})";
            $allTraces[] = "  • Jumlah Hari Lembur: {$holidayDays} hari";
            $allTraces[] = '  • Day Type: Hari Libur';
            $allTraces[] = "  • Batas Potongan: {$holidayThreshold} jam ({$holidayBreak['threshold_minutes']} menit)";
            $allTraces[] = "  • Potongan Istirahat: {$holidayBreak['deduction']} jam" . ($holidayBreak['is_deducted'] ? " ({$holidayDays} hari × {$holidayDurationPerDay} jam)" : ' (< batas potongan)');
            $allTraces[] = "  • Jam Lembur Bersih: {$holidayNetHours} jam";
            if ($holidayEval) {
                foreach ($holidayEval['trace'] as $t) {
                    $allTraces[] = "  • {$t}";
                }
            }
        }

        $snapshot['holiday'] = [
            'config_id' => $holidayConfig?->id,
            'config_version' => $holidayConfig?->current_version ?? 1,
            'gross_hours' => $holidayGrossHours,
            'days' => $holidayDays,
            'break_threshold' => $holidayThreshold,
            'break_duration_per_day' => $holidayDurationPerDay,
            'break_deduction' => $holidayBreak['deduction'],
            'net_hours' => $holidayNetHours,
            'hours' => $holidayNetHours,
            'formula_snapshot' => $holidayFormula,
            'result' => $holidayResult,
            'trace' => $holidayEval['trace'] ?? [],
        ];

        // 7. Combine totals using bcadd
        $totalOvertimePay = bcadd($workingResult, $holidayResult, 2);
        $totalGrossHours = round($workingDayGrossHours + $holidayGrossHours, 4);
        $totalBreakDeduction = round($workingBreak['deduction'] + $holidayBreak['deduction'], 4);
        $totalNetHours = round($workingDayNetHours + $holidayNetHours, 4);

        $snapshot['total_gross_hours'] = $totalGrossHours;
        $snapshot['total_break_deduction'] = $totalBreakDeduction;
        $snapshot['total_hours'] = $totalNetHours;
        $snapshot['total_net_hours'] = $totalNetHours;
        $snapshot['total_result'] = $totalOvertimePay;
        $snapshot['calculation_trace'] = $allTraces;

        return [
            'amount' => $totalOvertimePay,
            'rate' => number_format($rate, 2, '.', ''),
            'quantity' => number_format($totalNetHours, 2, '.', ''),
            'gross_quantity' => number_format($totalGrossHours, 2, '.', ''),
            'break_deduction' => number_format($totalBreakDeduction, 2, '.', ''),
            'snapshot' => $snapshot,
            'trace' => $allTraces,
        ];
    }

    /**
     * Get formula preview details and human-readable text.
     * Serves as single source of truth for preview representations.
     *
     * @param  array<string, mixed>  $formula
     * @param  string  $dayType
     * @return array{
     *     title: string,
     *     day_type: string,
     *     is_holiday: bool,
     *     threshold_hours: float,
     *     threshold_minutes: int,
     *     duration_per_day: float,
     *     rule_description: string,
     *     text: string,
     *     examples: array<int, array{time: string, deducted: bool, label: string}>
     * }
     */
    public function getFormulaPreview(array $formula, string $dayType = 'working_day'): array
    {
        $isHoliday = in_array($dayType, ['holiday', 'national_holiday', 'joint_leave', 'weekend'], true);
        $threshold = isset($formula['break_threshold'])
            ? (float) $formula['break_threshold']
            : ($isHoliday ? 4.0 : 3.0);
        $durationPerDay = isset($formula['break_duration_per_day'])
            ? (float) $formula['break_duration_per_day']
            : ($isHoliday ? 1.0 : 0.5);
        $thresholdMinutes = (int) round($threshold * 60);
        $thresholdDisplay = ($threshold == (int) $threshold) ? (int) $threshold : $threshold;

        $title = $isHoliday ? 'LEMBUR HARI LIBUR' : 'LEMBUR HARI BIASA';
        $ruleDescription = "Potongan mulai berlaku pada lembur ≥ {$thresholdDisplay} jam.";

        $formatMinutes = function (int $m): string {
            $h = intdiv($m, 60);
            $rem = $m % 60;
            return sprintf('%d:%02d', $h, $rem);
        };

        $tMinus1 = $formatMinutes(max($thresholdMinutes - 1, 0));
        $tExact = $formatMinutes($thresholdMinutes);
        $tPlus1 = $formatMinutes($thresholdMinutes + 1);
        $tLater = $formatMinutes($thresholdMinutes + 60);

        $sampleTimes = [$tMinus1, $tExact, $tPlus1, $tLater];
        $examples = [];
        foreach ($sampleTimes as $timeStr) {
            $calc = $this->calculateBreakDeduction($timeStr, 1.0, $dayType, $threshold, $durationPerDay);
            $examples[] = [
                'time' => $timeStr,
                'deducted' => $calc['is_deducted'],
                'label' => $calc['is_deducted'] ? 'Dipotong' : 'Tidak dipotong',
                'deduction' => $calc['deduction'],
                'net_hours' => $calc['net_hours'],
            ];
        }

        $text = $this->formulaEvaluator->toHumanReadable($formula, $dayType);

        return [
            'title' => $title,
            'day_type' => $dayType,
            'is_holiday' => $isHoliday,
            'threshold_hours' => $threshold,
            'threshold_minutes' => $thresholdMinutes,
            'duration_per_day' => $durationPerDay,
            'rule_description' => $ruleDescription,
            'operator' => '>=',
            'text' => $text,
            'examples' => $examples,
        ];
    }

    /**
     * Compute comprehensive overtime recap for an employee and their entries in a period.
     * Separates gross hours, break deductions, gross pay, deduction nominals, and net pay.
     *
     * @param  \Illuminate\Support\Collection|array  $entries
     * @return array<string, mixed>
     */
    public function calculateEmployeeOvertimeRecap(Employee $employee, mixed $entries): array
    {
        $entriesList = is_array($entries) ? $entries : $entries->all();

        $wdMinutes = 0;
        $wdDates = [];
        $hlMinutes = 0;
        $hlDates = [];

        foreach ($entriesList as $entry) {
            $dayType = is_array($entry) ? ($entry['day_type'] ?? 'working_day') : $entry->day_type;
            $durationMin = is_array($entry) ? ($entry['duration_minutes'] ?? 0) : $entry->duration_minutes;
            $date = is_array($entry) ? ($entry['date'] ?? '') : ($entry->date instanceof Carbon ? $entry->date->toDateString() : (string) $entry->date);

            if ($dayType === 'holiday') {
                $hlMinutes += (int) $durationMin;
                $hlDates[$date] = true;
            } else {
                $wdMinutes += (int) $durationMin;
                $wdDates[$date] = true;
            }
        }

        $wdDays = count($wdDates);
        $hlDays = count($hlDates);

        $calcResult = $this->calculateForEmployee($employee, [
            'working_day_minutes' => $wdMinutes,
            'working_day_days' => $wdDays,
            'holiday_minutes' => $hlMinutes,
            'holiday_days' => $hlDays,
        ]);

        $rate = (float) ($employee->default_overtime_rate ?? 0);
        $snapshot = $calcResult['snapshot'];

        $wdGrossHours = (float) ($snapshot['working_day']['gross_hours'] ?? 0);
        $wdNetHours = (float) ($snapshot['working_day']['net_hours'] ?? 0);
        $wdBreakDeduction = (float) ($snapshot['working_day']['break_deduction'] ?? 0);
        $wdThreshold = (float) ($snapshot['working_day']['break_threshold'] ?? 3.0);
        $wdThresholdReached = ($wdMinutes >= (int) round($wdThreshold * 60)) && ($wdDays > 0);
        $wdPay = (string) ($snapshot['working_day']['result'] ?? '0.00');

        $hlGrossHours = (float) ($snapshot['holiday']['gross_hours'] ?? 0);
        $hlNetHours = (float) ($snapshot['holiday']['net_hours'] ?? 0);
        $hlBreakDeduction = (float) ($snapshot['holiday']['break_deduction'] ?? 0);
        $hlThreshold = (float) ($snapshot['holiday']['break_threshold'] ?? 4.0);
        $hlThresholdReached = ($hlMinutes >= (int) round($hlThreshold * 60)) && ($hlDays > 0);
        $hlPay = (string) ($snapshot['holiday']['result'] ?? '0.00');

        // Evaluate gross pay before break deductions to calculate exact break deduction nominal
        $workingFormula = $snapshot['working_day']['formula_snapshot'] ?? $this->getDefaultWorkingDayFormula();
        $holidayFormula = $snapshot['holiday']['formula_snapshot'] ?? $this->getDefaultHolidayFormula();

        $grossWdPay = '0.00';
        if ($wdGrossHours > 0 && $rate > 0) {
            $grossWdEval = $this->formulaEvaluator->evaluate($workingFormula, [
                'employee_overtime_rate' => $rate,
                'overtime_days' => $wdDays,
                'total_overtime_hours' => $wdGrossHours,
            ]);
            $grossWdPay = $grossWdEval['result'];
        }

        $grossHlPay = '0.00';
        if ($hlGrossHours > 0 && $rate > 0) {
            $grossHlEval = $this->formulaEvaluator->evaluate($holidayFormula, [
                'employee_overtime_rate' => $rate,
                'total_overtime_hours' => $hlGrossHours,
            ]);
            $grossHlPay = $grossHlEval['result'];
        }

        $nominalPotonganWd = bcsub($grossWdPay, $wdPay, 2);
        $nominalPotonganHl = bcsub($grossHlPay, $hlPay, 2);
        $totalNominalPotongan = bcadd($nominalPotonganWd, $nominalPotonganHl, 2);
        $totalUpahBruto = bcadd($grossWdPay, $grossHlPay, 2);
        $totalUpahBersih = $calcResult['amount'];

        return [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'department' => $employee->department_snapshot ?? $employee->department?->name ?? (string) $employee->department,
            'rate' => $rate,
            'rate_formatted' => 'Rp ' . number_format($rate, 0, ',', '.'),
            'entries_count' => count($entriesList),
            'working_day' => [
                'entries_count' => count(array_filter($entriesList, fn ($e) => (is_array($e) ? $e['day_type'] : $e->day_type) === 'working_day')),
                'gross_minutes' => $wdMinutes,
                'gross_hours' => $wdGrossHours,
                'days' => $wdDays,
                'threshold' => $wdThreshold,
                'threshold_reached' => $wdThresholdReached,
                'break_deduction_hours' => $wdBreakDeduction,
                'net_hours' => $wdNetHours,
                'gross_pay' => $grossWdPay,
                'gross_pay_formatted' => 'Rp ' . number_format((float) $grossWdPay, 0, ',', '.'),
                'deduction_amount' => $nominalPotonganWd,
                'deduction_amount_formatted' => 'Rp ' . number_format((float) $nominalPotonganWd, 0, ',', '.'),
                'pay' => $wdPay,
                'pay_formatted' => 'Rp ' . number_format((float) $wdPay, 0, ',', '.'),
            ],
            'holiday' => [
                'entries_count' => count(array_filter($entriesList, fn ($e) => (is_array($e) ? $e['day_type'] : $e->day_type) === 'holiday')),
                'gross_minutes' => $hlMinutes,
                'gross_hours' => $hlGrossHours,
                'days' => $hlDays,
                'threshold' => $hlThreshold,
                'threshold_reached' => $hlThresholdReached,
                'break_deduction_hours' => $hlBreakDeduction,
                'net_hours' => $hlNetHours,
                'gross_pay' => $grossHlPay,
                'gross_pay_formatted' => 'Rp ' . number_format((float) $grossHlPay, 0, ',', '.'),
                'deduction_amount' => $nominalPotonganHl,
                'deduction_amount_formatted' => 'Rp ' . number_format((float) $nominalPotonganHl, 0, ',', '.'),
                'pay' => $hlPay,
                'pay_formatted' => 'Rp ' . number_format((float) $hlPay, 0, ',', '.'),
            ],
            'total_gross_hours' => (float) ($snapshot['total_gross_hours'] ?? 0),
            'total_break_deduction_hours' => (float) ($snapshot['total_break_deduction'] ?? 0),
            'total_net_hours' => (float) ($snapshot['total_net_hours'] ?? 0),
            'total_upah_bruto' => $totalUpahBruto,
            'total_upah_bruto_formatted' => 'Rp ' . number_format((float) $totalUpahBruto, 0, ',', '.'),
            'total_potongan_lembur' => $totalNominalPotongan,
            'total_potongan_lembur_formatted' => 'Rp ' . number_format((float) $totalNominalPotongan, 0, ',', '.'),
            'total_upah_lembur' => $totalUpahBersih,
            'total_upah_lembur_formatted' => 'Rp ' . number_format((float) $totalUpahBersih, 0, ',', '.'),
            'total_bersih_lembur' => $totalUpahBersih,
            'trace' => $calcResult['trace'],
        ];
    }
}

