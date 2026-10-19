<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SecurityAttendance;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SecurityPayrollCalculationService
{
    public function __construct(
        protected PayrollCalculationService $calculationService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Calculate security overtime components using the business formula.
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
        // Biaya jam pertama = firstHours * hourlyRate * multFirst
        $firstHoursCostRaw = $firstHours * $hourlyRate * $multFirst;
        $firstHoursCost = $this->applyRounding($firstHoursCostRaw, $config->round_rule);

        // Biaya jam berikutnya = subsequentHours * hourlyRate * multSubsequent
        $subsequentHoursCostRaw = $subsequentHours * $hourlyRate * $multSubsequent;
        $subsequentHoursCost = $this->applyRounding($subsequentHoursCostRaw, $config->round_rule);

        $totalOvertimeCost = $firstHoursCost + $subsequentHoursCost;

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
     * Calculate and summarize security attendance and overtime for an employee in a period.
     *
     * @param  array{gross_overtime_hours?: float, break_deduction_hours?: float}  $overrides
     */
    public function calculateForEmployee(
        Employee $employee,
        PayrollPeriod $period,
        ?SecurityPayrollConfig $config = null,
        array $overrides = []
    ): SecurityOvertimeSummary {
        $config = $config ?? SecurityPayrollConfig::getActiveConfig();
        $periodDates = $this->getPeriodDates($period);
        $totalDays = count($periodDates);

        // Fetch all attendance rows for this employee & period
        $attendances = SecurityAttendance::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->get()
            ->keyBy(fn ($a) => $a->date instanceof Carbon ? $a->date->format('Y-m-d') : Carbon::parse($a->date)->format('Y-m-d'));

        $totalWorkDays = 0;
        $totalOffDays = 0;
        $totalAbsentDays = 0;
        $totalWorkHours = 0.0;
        $computedGrossOvertime = 0.0;

        foreach ($periodDates as $d) {
            $key = $d->format('Y-m-d');
            $att = $attendances->get($key);

            if ($att) {
                if ($att->status === 'off') {
                    $totalOffDays++;
                } elseif ($att->status === 'absent') {
                    $totalAbsentDays++;
                } else {
                    $totalWorkDays++;
                }
                $totalWorkHours += (float) $att->work_hours;
                $computedGrossOvertime += (float) $att->overtime_hours;
            } else {
                // Default absent / unset, but if unrecorded we treat as present standard 8h
                $totalWorkDays++;
                $totalWorkHours += 8.0;
            }
        }

        // Allow manual overrides for gross overtime or break deduction if provided
        $grossOvertime = array_key_exists('gross_overtime_hours', $overrides)
            ? (float) $overrides['gross_overtime_hours']
            : $computedGrossOvertime;

        $existingSummary = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $breakDeduction = array_key_exists('break_deduction_hours', $overrides)
            ? (float) $overrides['break_deduction_hours']
            : ($existingSummary ? (float) $existingSummary->break_deduction_hours : (float) $config->break_deduction_default);

        $calc = $this->calculateOvertime($grossOvertime, $breakDeduction, $config);

        // Base salary + fixed allowances for total period salary
        $gajiPokok = (float) ($employee->gaji_pokok ?? 0);
        $tunjanganJabatan = (float) ($employee->tunjangan_jabatan ?? 0);
        $tunjanganKehadiran = (float) ($employee->tunjangan_kehadiran ?? 0);
        $tunjanganKeluarga = (float) ($employee->tunjangan_keluarga ?? 0);
        $baseEarnings = $gajiPokok + $tunjanganJabatan + $tunjanganKehadiran + $tunjanganKeluarga;
        $totalPeriodSalary = $baseEarnings + $calc['total_overtime_cost'];

        $snapshot = [
            'formula' => 'security_overtime_v'.$config->version,
            'config_id' => $config->id,
            'config_code' => $config->code,
            'config_version' => $config->version,
            'hourly_rate' => $calc['hourly_rate'],
            'first_hours_limit' => $calc['first_hours_limit'],
            'first_hours_multiplier' => $calc['first_hours_multiplier'],
            'subsequent_hours_multiplier' => $calc['subsequent_hours_multiplier'],
            'break_deduction_hours' => $calc['break_deduction_hours'],
            'gross_overtime_hours' => $calc['gross_overtime_hours'],
            'net_overtime_hours' => $calc['net_overtime_hours'],
            'first_hours' => $calc['first_hours'],
            'first_hours_cost' => $calc['first_hours_cost'],
            'subsequent_hours' => $calc['subsequent_hours'],
            'subsequent_hours_cost' => $calc['subsequent_hours_cost'],
            'total_overtime_cost' => $calc['total_overtime_cost'],
            'calculated_at' => now()->toIso8601String(),
            'calculated_by' => Auth::id(),
        ];

        return SecurityOvertimeSummary::updateOrCreate(
            [
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ],
            [
                'total_days' => $totalDays,
                'total_work_days' => $totalWorkDays,
                'total_off_days' => $totalOffDays,
                'total_absent_days' => $totalAbsentDays,
                'total_work_hours' => round($totalWorkHours, 2),
                'gross_overtime_hours' => $calc['gross_overtime_hours'],
                'break_deduction_hours' => $calc['break_deduction_hours'],
                'net_overtime_hours' => $calc['net_overtime_hours'],
                'hourly_rate' => $calc['hourly_rate'],
                'first_hours_limit' => $calc['first_hours_limit'],
                'first_hours_multiplier' => $calc['first_hours_multiplier'],
                'subsequent_hours_multiplier' => $calc['subsequent_hours_multiplier'],
                'first_hours' => $calc['first_hours'],
                'first_hours_cost' => $calc['first_hours_cost'],
                'subsequent_hours' => $calc['subsequent_hours'],
                'subsequent_hours_cost' => $calc['subsequent_hours_cost'],
                'total_overtime_cost' => $calc['total_overtime_cost'],
                'total_period_salary' => round($totalPeriodSalary, 2),
                'config_version' => $config->version,
                'calculation_snapshot' => $snapshot,
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

        $payslip->update([
            'basic_salary' => $totals['basic_salary'],
            'total_earnings' => $totals['total_earnings'],
            'total_deductions' => $totals['total_deductions'],
            'net_salary' => $totals['net_salary'],
            'gaji_bruto' => $totals['gaji_bruto'],
            'uang_dinas' => $totals['uang_dinas'],
        ]);

        return $payslip->fresh(['items']);
    }

    /**
     * Apply rounding rule to numerical amount.
     */
    protected function applyRounding(float $amount, string $rule = 'round'): float
    {
        return match ($rule) {
            'ceil' => ceil($amount),
            'floor' => floor($amount),
            default => round($amount),
        };
    }
}
