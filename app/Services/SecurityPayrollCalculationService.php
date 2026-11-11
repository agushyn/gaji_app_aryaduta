<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SecurityAttendance;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Services\Security\SecurityAttendanceCalculator;
use App\Services\Security\SecurityOvertimeCalculator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;

class SecurityPayrollCalculationService
{
    public function __construct(
        protected PayrollCalculationService $calculationService,
        protected AuditLogService $auditLogService,
        protected SecurityAttendanceCalculator $attendanceCalculator,
        protected SecurityOvertimeCalculator $overtimeCalculator
    ) {}

    /**
     * Calculate security overtime components using the business formula.
     * Preserved for backward compatibility and unit tests.
     *
     * @return array{
     *     gross_overtime_hours: float,
     *     break_deduction_hours: float,
     *     net_overtime_hours: float,
     *     hourly_rate: float,
     *     first_hours_limit: float,
     *     first_hours_multiplier: float,
     *     subsequent_hours_multiplier: float,
     *     first_hours: float,
     *     first_hours_cost: float,
     *     subsequent_hours: float,
     *     subsequent_hours_cost: float,
     *     total_overtime_cost: float
     * }
     */
    public function calculateOvertime(
        float $grossOvertimeHours,
        float $breakDeductionHours = 0.0,
        ?SecurityPayrollConfig $config = null
    ): array {
        $config = $config ?? SecurityPayrollConfig::getActiveConfig();

        // 1. Sanitize inputs (never negative)
        $gross = max(0.0, round($grossOvertimeHours, 2));
        $deduction = max(0.0, round($breakDeductionHours, 2));

        // Enforce max break deduction from config if defined
        if ($config->max_break_deduction > 0) {
            $deduction = min($deduction, (float) $config->max_break_deduction);
        }

        // 2. Net overtime hours = MAX(0, gross - deduction)
        $net = max(0.0, round($gross - $deduction, 2));

        // 3. Rate and multipliers from config
        $hourlyRate = (float) $config->hourly_rate;
        $limit = (float) $config->first_hours_limit;
        $multFirst = (float) $config->first_hours_multiplier;
        $multSubsequent = (float) $config->subsequent_hours_multiplier;

        // 4. First hours = MIN(net, limit)
        $firstHours = min($net, $limit);

        // 5. Subsequent hours = MAX(0, net - firstHours)
        $subsequentHours = max(0.0, round($net - $firstHours, 2));

        // 6. Cost calculations using precise rounding
        $firstHoursCostRaw = bcmul(bcmul((string) $firstHours, (string) $hourlyRate, 4), (string) $multFirst, 4);
        $firstHoursCost = (float) $this->applyRounding($firstHoursCostRaw, $config->round_rule);

        $subsequentHoursCostRaw = bcmul(bcmul((string) $subsequentHours, (string) $hourlyRate, 4), (string) $multSubsequent, 4);
        $subsequentHoursCost = (float) $this->applyRounding($subsequentHoursCostRaw, $config->round_rule);

        $totalOvertimeCost = (float) bcadd((string) $firstHoursCost, (string) $subsequentHoursCost, 2);

        return [
            'gross_overtime_hours' => $gross,
            'break_deduction_hours' => $deduction,
            'net_overtime_hours' => $net,
            'hourly_rate' => $hourlyRate,
            'first_hours_limit' => $limit,
            'first_hours_multiplier' => $multFirst,
            'subsequent_hours_multiplier' => $multSubsequent,
            'first_hours' => $firstHours,
            'first_hours_cost' => $firstHoursCost,
            'subsequent_hours' => $subsequentHours,
            'subsequent_hours_cost' => $subsequentHoursCost,
            'total_overtime_cost' => $totalOvertimeCost,
        ];
    }

    /**
     * Generate or retrieve all dynamic dates in a payroll period.
     *
     * @return array<int, Carbon>
     */
    public function getPeriodDates(PayrollPeriod $period): array
    {
        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->startOfDay();

        $dates = [];
        $carbonPeriod = CarbonPeriod::create($start, '1 day', $end);

        foreach ($carbonPeriod as $date) {
            $dates[] = $date->copy();
        }

        return $dates;
    }

    /**
     * Calculate all metrics for an employee in a period.
     * Core calculation logic shared between persistence and preview.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function computeMetrics(
        Employee $employee,
        PayrollPeriod $period,
        ?SecurityPayrollConfig $config = null,
        array $overrides = []
    ): array {
        $config = $config ?? SecurityPayrollConfig::getActiveConfig();
        $periodDates = $this->getPeriodDates($period);
        $calendarDays = count($periodDates);

        // Fetch daily attendance rows
        $attendances = SecurityAttendance::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->get()
            ->keyBy(fn ($a) => $a->date instanceof Carbon ? $a->date->format('Y-m-d') : Carbon::parse($a->date)->format('Y-m-d'));

        $existingSummary = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        // 1. Resolve attendance counts
        $presentDays = 0;
        $offDays = 0;
        $sickDays = 0;
        $permissionDays = 0;
        $leaveDays = 0;
        $absentDays = 0;

        $regularOvertimeDays = 0;
        $regularOvertimeHours = 0.0;
        $offHolidayOvertimeDays = 0;
        $offHolidayOvertimeHours = 0.0;

        $hasSimplifiedInput = array_key_exists('present_days', $overrides)
            || array_key_exists('total_work_days', $overrides)
            || array_key_exists('regular_overtime_hours', $overrides)
            || array_key_exists('gross_overtime_hours', $overrides);

        if ($hasSimplifiedInput) {
            $presentDays = array_key_exists('present_days', $overrides)
                ? max(0, (int) $overrides['present_days'])
                : (array_key_exists('total_work_days', $overrides) ? max(0, (int) $overrides['total_work_days']) : $calendarDays);

            $offDays = array_key_exists('off_days', $overrides)
                ? max(0, (int) $overrides['off_days'])
                : (array_key_exists('total_off_days', $overrides) ? max(0, (int) $overrides['total_off_days']) : 0);

            $sickDays = array_key_exists('sick_days', $overrides) ? max(0, (int) $overrides['sick_days']) : 0;
            $permissionDays = array_key_exists('permission_days', $overrides) ? max(0, (int) $overrides['permission_days']) : 0;
            $leaveDays = array_key_exists('leave_days', $overrides) ? max(0, (int) $overrides['leave_days']) : 0;

            $absentDays = array_key_exists('absent_days', $overrides)
                ? max(0, (int) $overrides['absent_days'])
                : (! array_key_exists('present_days', $overrides) && array_key_exists('total_work_days', $overrides)
                    ? max(0, $calendarDays - $presentDays - $offDays)
                    : 0);

            $regularOvertimeDays = array_key_exists('regular_overtime_days', $overrides)
                ? max(0, (int) $overrides['regular_overtime_days'])
                : 0;

            $regularOvertimeHours = array_key_exists('regular_overtime_hours', $overrides)
                ? max(0.0, (float) $overrides['regular_overtime_hours'])
                : (array_key_exists('gross_overtime_hours', $overrides) ? max(0.0, (float) $overrides['gross_overtime_hours']) : 0.0);

            $offHolidayOvertimeDays = array_key_exists('off_holiday_overtime_days', $overrides)
                ? max(0, (int) $overrides['off_holiday_overtime_days'])
                : 0;

            $offHolidayOvertimeHours = array_key_exists('off_holiday_overtime_hours', $overrides)
                ? max(0.0, (float) $overrides['off_holiday_overtime_hours'])
                : 0.0;
        } elseif ($attendances->isEmpty() && $existingSummary) {
            $presentDays = (int) ($existingSummary->present_days ?: $existingSummary->total_work_days);
            $offDays = (int) ($existingSummary->off_days ?: $existingSummary->total_off_days);
            $sickDays = (int) $existingSummary->sick_days;
            $permissionDays = (int) $existingSummary->permission_days;
            $leaveDays = (int) $existingSummary->leave_days;
            $absentDays = (int) ($existingSummary->absent_days ?: $existingSummary->total_absent_days);

            $regularOvertimeDays = (int) $existingSummary->regular_overtime_days;
            $regularOvertimeHours = (float) ($existingSummary->regular_overtime_hours > 0 ? $existingSummary->regular_overtime_hours : $existingSummary->gross_overtime_hours);
            $offHolidayOvertimeDays = (int) $existingSummary->off_holiday_overtime_days;
            $offHolidayOvertimeHours = (float) $existingSummary->off_holiday_overtime_hours;
        } else {
            foreach ($periodDates as $d) {
                $key = $d->format('Y-m-d');
                $att = $attendances->get($key);

                if ($att) {
                    if ($att->status === 'off') {
                        $offDays++;
                    } elseif ($att->status === 'sick') {
                        $sickDays++;
                    } elseif ($att->status === 'leave') {
                        $leaveDays++;
                    } elseif ($att->status === 'absent') {
                        $absentDays++;
                    } else {
                        $presentDays++;
                    }
                    $regularOvertimeHours += (float) $att->overtime_hours;
                    if ((float) $att->overtime_hours > 0) {
                        $regularOvertimeDays++;
                    }
                } else {
                    $presentDays++;
                }
            }
        }

        // 2. Attendance & Target Hours calculations
        $calendarHours = $this->attendanceCalculator->calculateCalendarHours($calendarDays);
        $totalAbsenceDays = $sickDays + $permissionDays + $leaveDays + $absentDays;
        $targetHours = $this->attendanceCalculator->calculateTargetHours($calendarDays, $offDays, $totalAbsenceDays);
        $attendanceHours = $this->attendanceCalculator->calculateAttendanceHours($presentDays);
        $currentShortage = $this->attendanceCalculator->calculateCurrentShortage($targetHours, $attendanceHours);

        // 3. Carry-Forward Shortage Resolution
        $manualPrevShortage = array_key_exists('previous_shortage_hours', $overrides)
            ? (float) $overrides['previous_shortage_hours']
            : ($existingSummary ? (float) $existingSummary->previous_shortage_hours : null);

        $prevShortageResult = $this->attendanceCalculator->resolvePreviousShortage($employee, $period, $manualPrevShortage);
        $previousShortage = $prevShortageResult['previous_shortage_hours'];
        $previousPeriod = $prevShortageResult['previous_period'];
        $totalShortage = $this->attendanceCalculator->calculateTotalShortage($currentShortage, $previousShortage);

        // 4. Shortage Offset against Overtime (Excel cell AU = IF(AQ > AP, AQ - AT - AP, 0))
        $offsetResult = $this->attendanceCalculator->applyShortageOffset($totalShortage, $regularOvertimeHours);
        $shortageOffset = $offsetResult['shortage_offset_hours'];
        $adjustedRegularOvertime = $offsetResult['adjusted_overtime_hours'];
        $remainingShortage = $offsetResult['remaining_shortage_hours'];

        $customBreakDeduction = array_key_exists('break_deduction_hours', $overrides)
            ? (float) $overrides['break_deduction_hours']
            : ($existingSummary ? (float) $existingSummary->break_deduction_hours : null);

        // 5. Regular Overtime Calculation
        $regResult = $this->overtimeCalculator->calculateRegularOvertime(
            $regularOvertimeDays,
            $adjustedRegularOvertime,
            $config,
            $customBreakDeduction
        );

        // 6. OFF / Holiday Overtime Calculation (Strict Separation & Unconfirmed Rate Guard)
        $rateConfirmed = array_key_exists('off_holiday_rate_confirmed', $overrides)
            ? (bool) $overrides['off_holiday_rate_confirmed']
            : (bool) ($existingSummary?->off_holiday_rate_confirmed ?? false);
        $offResult = $this->overtimeCalculator->calculateOffHolidayOvertime(
            $offHolidayOvertimeDays,
            $offHolidayOvertimeHours,
            $config,
            $rateConfirmed
        );

        // 7. Financial aggregation
        $totalOvertimeCostStr = bcadd(
            number_format($regResult['total_cost'], 2, '.', ''),
            number_format($offResult['total_cost'], 2, '.', ''),
            2
        );
        $totalOvertimeCost = (float) $totalOvertimeCostStr;

        $gajiPokok = (float) ($employee->gaji_pokok ?? 0);
        $tunjanganJabatan = (float) ($employee->tunjangan_jabatan ?? 0);
        $tunjanganKehadiran = (float) ($employee->tunjangan_kehadiran ?? 0);
        $tunjanganKeluarga = (float) ($employee->tunjangan_keluarga ?? 0);
        $baseEarnings = $gajiPokok + $tunjanganJabatan + $tunjanganKehadiran + $tunjanganKeluarga;
        $totalPeriodSalary = $baseEarnings + $totalOvertimeCost;

        $grossOvertimeHoursTotal = $regularOvertimeHours + $offHolidayOvertimeHours;
        $breakDeductionHoursTotal = $regResult['break_deduction_hours'] + $offResult['break_deduction_hours'];
        $netOvertimeHoursTotal = $regResult['net_hours'] + $offResult['net_hours'];

        // Snapshot representation
        $snapshot = [
            'formula' => 'security_calculation_engine_v2',
            'config' => [
                'id' => $config->id,
                'code' => $config->code,
                'version' => $config->version,
                'hourly_rate' => (float) $config->hourly_rate,
                'first_hours_multiplier' => (float) $config->first_hours_multiplier,
                'subsequent_hours_multiplier' => (float) $config->subsequent_hours_multiplier,
                'holiday_multiplier' => (float) $config->holiday_multiplier,
                'round_rule' => $config->round_rule,
            ],
            'attendance' => [
                'calendar_days' => $calendarDays,
                'calendar_hours' => $calendarHours,
                'present_days' => $presentDays,
                'attendance_hours' => $attendanceHours,
                'off_days' => $offDays,
                'off_hours' => $offDays * 8.0,
                'sick_days' => $sickDays,
                'permission_days' => $permissionDays,
                'leave_days' => $leaveDays,
                'absent_days' => $absentDays,
                'total_absence_days' => $totalAbsenceDays,
                'absence_hours' => $totalAbsenceDays * 8.0,
                'target_hours' => $targetHours,
            ],
            'shortage' => [
                'current_shortage_hours' => $currentShortage,
                'previous_shortage_hours' => $previousShortage,
                'previous_payroll_period_id' => $previousPeriod?->id,
                'previous_period_code' => $previousPeriod?->period_code,
                'total_shortage_hours' => $totalShortage,
                'shortage_offset_hours' => $shortageOffset,
                'remaining_shortage_hours' => $remainingShortage,
                'carry_forward_source' => $prevShortageResult['source'],
            ],
            'regular_overtime' => $regResult,
            'off_holiday_overtime' => $offResult,
            'totals' => [
                'gross_overtime_hours' => $grossOvertimeHoursTotal,
                'break_deduction_hours' => $breakDeductionHoursTotal,
                'net_overtime_hours' => $netOvertimeHoursTotal,
                'total_overtime_cost' => $totalOvertimeCost,
                'base_salary' => $baseEarnings,
                'total_period_salary' => round($totalPeriodSalary, 2),
            ],
            'calculated_at' => now()->toIso8601String(),
            'calculated_by' => Auth::id(),
        ];

        return [
            'config' => $config,
            'calendar_days' => $calendarDays,
            'present_days' => $presentDays,
            'off_days' => $offDays,
            'sick_days' => $sickDays,
            'permission_days' => $permissionDays,
            'leave_days' => $leaveDays,
            'absent_days' => $absentDays,
            'total_absent_days' => $totalAbsenceDays,
            'attendance_hours' => $attendanceHours,
            'target_hours' => $targetHours,
            'current_shortage_hours' => $currentShortage,
            'previous_shortage_hours' => $previousShortage,
            'previous_payroll_period_id' => $previousPeriod?->id,
            'total_shortage_hours' => $totalShortage,
            'shortage_offset_hours' => $shortageOffset,
            'remaining_shortage_hours' => $remainingShortage,
            'regular_overtime_days' => $regularOvertimeDays,
            'regular_overtime_hours' => $regularOvertimeHours,
            'regular_break_deduction_hours' => $regResult['break_deduction_hours'],
            'regular_net_overtime_hours' => $regResult['net_hours'],
            'regular_first_hours' => $regResult['first_hours'],
            'regular_first_hours_cost' => $regResult['first_hours_cost'],
            'regular_subsequent_hours' => $regResult['subsequent_hours'],
            'regular_subsequent_hours_cost' => $regResult['subsequent_hours_cost'],
            'regular_overtime_cost' => $regResult['total_cost'],
            'off_holiday_overtime_days' => $offHolidayOvertimeDays,
            'off_holiday_overtime_hours' => $offHolidayOvertimeHours,
            'off_holiday_break_deduction_hours' => $offResult['break_deduction_hours'],
            'off_holiday_net_overtime_hours' => $offResult['net_hours'],
            'off_holiday_multiplier' => $offResult['multiplier'],
            'off_holiday_overtime_cost' => $offResult['total_cost'],
            'off_holiday_rate_confirmed' => $offResult['rate_confirmed'],
            'off_holiday_rate_status_message' => $offResult['rate_status_message'],
            'off_holiday_estimated_cost' => $offResult['estimated_cost'],
            'gross_overtime_hours' => $grossOvertimeHoursTotal,
            'break_deduction_hours' => $breakDeductionHoursTotal,
            'net_overtime_hours' => $netOvertimeHoursTotal,
            'hourly_rate' => $regResult['hourly_rate'],
            'first_hours_limit' => $regResult['first_hours_limit'],
            'first_hours_multiplier' => $regResult['first_hours_multiplier'],
            'subsequent_hours_multiplier' => $regResult['subsequent_hours_multiplier'],
            'first_hours' => $regResult['first_hours'],
            'first_hours_cost' => $regResult['first_hours_cost'],
            'subsequent_hours' => $regResult['subsequent_hours'],
            'subsequent_hours_cost' => $regResult['subsequent_hours_cost'],
            'total_overtime_cost' => $totalOvertimeCost,
            'total_period_salary' => round($totalPeriodSalary, 2),
            'snapshot' => $snapshot,
        ];
    }

    /**
     * Preview calculation for an employee in a period without persisting to database.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function previewForEmployee(
        Employee $employee,
        PayrollPeriod $period,
        ?SecurityPayrollConfig $config = null,
        array $overrides = []
    ): array {
        $metrics = $this->computeMetrics($employee, $period, $config, $overrides);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'payroll_period_id' => $period->id,
            'period_code' => $period->period_code,
            'metrics' => $metrics,
            'snapshot' => $metrics['snapshot'],
        ];
    }

    /**
     * Calculate and summarize security attendance and overtime for an employee in a period.
     * Persists results to security_overtime_summaries.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function calculateForEmployee(
        Employee $employee,
        PayrollPeriod $period,
        ?SecurityPayrollConfig $config = null,
        array $overrides = []
    ): SecurityOvertimeSummary {
        $metrics = $this->computeMetrics($employee, $period, $config, $overrides);
        $config = $metrics['config'];

        return SecurityOvertimeSummary::updateOrCreate(
            [
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ],
            [
                'present_days' => $metrics['present_days'],
                'off_days' => $metrics['off_days'],
                'sick_days' => $metrics['sick_days'],
                'permission_days' => $metrics['permission_days'],
                'leave_days' => $metrics['leave_days'],
                'absent_days' => $metrics['absent_days'],
                'attendance_hours' => $metrics['attendance_hours'],
                'target_hours' => $metrics['target_hours'],
                'current_shortage_hours' => $metrics['current_shortage_hours'],
                'previous_shortage_hours' => $metrics['previous_shortage_hours'],
                'total_shortage_hours' => $metrics['total_shortage_hours'],
                'shortage_offset_hours' => $metrics['shortage_offset_hours'],
                'remaining_shortage_hours' => $metrics['remaining_shortage_hours'],
                'previous_payroll_period_id' => $metrics['previous_payroll_period_id'],
                'regular_overtime_days' => $metrics['regular_overtime_days'],
                'regular_overtime_hours' => $metrics['regular_overtime_hours'],
                'regular_break_deduction_hours' => $metrics['regular_break_deduction_hours'],
                'regular_net_overtime_hours' => $metrics['regular_net_overtime_hours'],
                'regular_first_hours' => $metrics['regular_first_hours'],
                'regular_first_hours_cost' => $metrics['regular_first_hours_cost'],
                'regular_subsequent_hours' => $metrics['regular_subsequent_hours'],
                'regular_subsequent_hours_cost' => $metrics['regular_subsequent_hours_cost'],
                'regular_overtime_cost' => $metrics['regular_overtime_cost'],
                'off_holiday_overtime_days' => $metrics['off_holiday_overtime_days'],
                'off_holiday_overtime_hours' => $metrics['off_holiday_overtime_hours'],
                'off_holiday_break_deduction_hours' => $metrics['off_holiday_break_deduction_hours'],
                'off_holiday_net_overtime_hours' => $metrics['off_holiday_net_overtime_hours'],
                'off_holiday_multiplier' => $metrics['off_holiday_multiplier'],
                'off_holiday_overtime_cost' => $metrics['off_holiday_overtime_cost'],
                'off_holiday_rate_confirmed' => $metrics['off_holiday_rate_confirmed'],
                'total_days' => $metrics['calendar_days'],
                'total_work_days' => $metrics['present_days'],
                'total_off_days' => $metrics['off_days'],
                'total_absent_days' => $metrics['total_absent_days'],
                'total_work_hours' => $metrics['attendance_hours'],
                'gross_overtime_hours' => $metrics['gross_overtime_hours'],
                'break_deduction_hours' => $metrics['break_deduction_hours'],
                'net_overtime_hours' => $metrics['net_overtime_hours'],
                'hourly_rate' => $metrics['hourly_rate'],
                'first_hours_limit' => $metrics['first_hours_limit'],
                'first_hours_multiplier' => $metrics['first_hours_multiplier'],
                'subsequent_hours_multiplier' => $metrics['subsequent_hours_multiplier'],
                'first_hours' => $metrics['first_hours'],
                'first_hours_cost' => $metrics['first_hours_cost'],
                'subsequent_hours' => $metrics['subsequent_hours'],
                'subsequent_hours_cost' => $metrics['subsequent_hours_cost'],
                'total_overtime_cost' => $metrics['total_overtime_cost'],
                'total_period_salary' => $metrics['total_period_salary'],
                'config_version' => $config->version,
                'calculation_snapshot' => $metrics['snapshot'],
            ]
        );
    }

    /**
     * Synchronize security overtime calculation into the employee's draft payslip.
     */
    public function syncSecurityPayslip(PayrollPeriod $period, Employee $employee): ?Payslip
    {
        $payslip = Payslip::with('items')
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        // If no payslip exists yet, or if it cannot be modified (finalized/voided), return early
        if (! $payslip || ! $payslip->canBeModified()) {
            return $payslip;
        }

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $summary) {
            $summary = $this->calculateForEmployee($employee, $period);
        }

        $overtimeComp = PayComponent::where('code', 'OVERTIME')->first();
        $overtimeAmount = (float) $summary->total_overtime_cost;

        $item = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();

        if ($overtimeAmount > 0) {
            $note = "Lembur Security ({$summary->net_overtime_hours} jam bersih, jam 1: {$summary->first_hours} jam, jam berikutnya: {$summary->subsequent_hours} jam)";
            if ($summary->off_holiday_overtime_days > 0 || $summary->off_holiday_overtime_hours > 0) {
                $regCostFmt = number_format($summary->regular_overtime_cost, 0, ',', '.');
                $offCostFmt = $summary->off_holiday_rate_confirmed
                    ? 'Rp '.number_format($summary->off_holiday_overtime_cost, 0, ',', '.')
                    : 'Menunggu Konfirmasi Tarif';
                $note = "Lembur Reguler: {$summary->regular_net_overtime_hours} jam (Rp {$regCostFmt}) | Lembur Libur: {$summary->off_holiday_net_overtime_hours} jam ({$offCostFmt})";
            }

            $itemData = [
                'pay_component_id' => $overtimeComp?->id ?? $item?->pay_component_id,
                'item_code_snapshot' => 'OVERTIME',
                'description_snapshot' => $overtimeComp?->name ?? 'Uang Lembur',
                'item_type' => 'earning',
                'category' => 'variable',
                'quantity' => $summary->net_overtime_hours,
                'unit' => 'jam',
                'rate' => $summary->hourly_rate,
                'note' => $note,
                'amount' => number_format($overtimeAmount, 2, '.', ''),
                'calculation_config_id' => null,
                'calculation_config_version' => $summary->config_version,
                'calculation_snapshot' => $summary->calculation_snapshot,
            ];

            if ($item) {
                $item->update($itemData);
            } else {
                $itemData['sort_order'] = 10;
                $payslip->items()->create($itemData);
            }
        } elseif ($item) {
            $item->delete();
        }

        // Recalculate totals
        $itemsArray = $payslip->items()->get()->map(function ($it) {
            return [
                'item_code_snapshot' => $it->item_code_snapshot,
                'description_snapshot' => $it->description_snapshot,
                'item_type' => $it->item_type,
                'amount' => $it->amount,
            ];
        })->toArray();

        $totals = $this->calculationService->calculate($itemsArray);

        $wasSubmittedOrApproved = in_array($payslip->status, [
            \App\Enums\PayslipStatus::PENDING_REVIEW->value,
            \App\Enums\PayslipStatus::APPROVED->value,
        ], true);

        $updatePayload = [
            'basic_salary' => $totals['basic_salary'],
            'total_earnings' => $totals['total_earnings'],
            'total_deductions' => $totals['total_deductions'],
            'net_salary' => $totals['net_salary'],
            'gaji_bruto' => $totals['gaji_bruto'],
            'uang_dinas' => $totals['uang_dinas'],
        ];

        if ($summary->present_days > 0 || $summary->total_work_days > 0) {
            $updatePayload['working_days_snapshot'] = $summary->present_days ?: $summary->total_work_days;
        }
        if ($summary->absent_days !== null || $summary->total_absent_days !== null) {
            $updatePayload['absent_days'] = max(0, (float) ($summary->absent_days ?: $summary->total_absent_days));
        }

        if ($wasSubmittedOrApproved) {
            $updatePayload['status'] = \App\Enums\PayslipStatus::DRAFT->value;
            $updatePayload['approved_by'] = null;
            $updatePayload['approved_at'] = null;
            $updatePayload['approval_comment'] = null;
            $updatePayload['submitted_by'] = null;
            $updatePayload['submitted_at'] = null;
        }

        $payslip->update($updatePayload);

        return $payslip->fresh(['items']);
    }

    /**
     * Apply rounding rule to numerical amount.
     */
    protected function applyRounding(string|float $amount, string $rule = 'round'): string
    {
        $floatVal = (float) $amount;

        $rounded = match ($rule) {
            'ceil' => ceil($floatVal),
            'floor' => floor($floatVal),
            default => round($floatVal),
        };

        return number_format($rounded, 2, '.', '');
    }
}
