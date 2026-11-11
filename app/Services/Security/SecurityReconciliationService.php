<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SecurityOvertimeSummary;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SecurityReconciliationService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Run non-mutating reconciliation checks on security payroll for a period.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     period: array<string, mixed>,
     *     status: string,
     *     total_checked: int,
     *     matched_count: int,
     *     mismatched_count: int,
     *     results: array<int, array<string, mixed>>,
     *     mismatches: array<int, array<string, mixed>>,
     *     checked_at: string,
     *     checked_by: string
     * }
     */
    public function reconcilePeriod(PayrollPeriod $period, array $filters = []): array
    {
        $query = Employee::security()
            ->active()
            ->with(['office', 'position', 'division']);

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('employee_number', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['office_id']) && $filters['office_id'] !== 'all') {
            $query->where('office_id', $filters['office_id']);
        }

        $employees = $query->orderBy('full_name')->get();

        $employeeIds = $employees->pluck('id')->toArray();

        $summaries = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $payslips = Payslip::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->with('items')
            ->get()
            ->keyBy('employee_id');

        $checkedAt = now();
        $user = Auth::user();
        $userName = $user?->name ?? 'Sistem';

        $results = [];
        $mismatches = [];
        $matchedCount = 0;
        $mismatchedCount = 0;

        foreach ($employees as $emp) {
            $summary = $summaries->get($emp->id);
            $payslip = $payslips->get($emp->id);

            $empChecks = $this->reconcileEmployee($emp, $period, $summary, $payslip);

            $hasMismatch = false;
            foreach ($empChecks['checks'] as $check) {
                if ($check['status'] === 'MISMATCH') {
                    $hasMismatch = true;
                    $mismatches[] = array_merge($check, [
                        'employee_id' => $emp->id,
                        'employee_number' => $emp->employee_number,
                        'employee_name' => $emp->full_name,
                        'period_code' => $period->period_code,
                    ]);
                }
            }

            if ($hasMismatch) {
                $mismatchedCount++;
                $empChecks['overall_status'] = 'MISMATCH';
            } else {
                $matchedCount++;
                $empChecks['overall_status'] = 'MATCH';
            }

            $results[] = $empChecks;
        }

        $overallStatus = $mismatchedCount === 0 ? 'MATCH' : 'MISMATCH';

        // Log reconciliation execution in audit trail (read-only verification)
        $this->auditLogService->log(
            action: 'security_payroll.reconciliation_run',
            subject: $period,
            changes: [
                'period_code' => $period->period_code,
                'overall_status' => $overallStatus,
                'total_checked' => count($employees),
                'matched' => $matchedCount,
                'mismatched' => $mismatchedCount,
                'checked_by' => $userName,
            ]
        );

        return [
            'period' => [
                'id' => $period->id,
                'period_code' => $period->period_code,
                'status' => $period->status,
                'status_label' => $period->status_label,
            ],
            'status' => $overallStatus,
            'overall_status' => $overallStatus,
            'total_checked' => count($employees),
            'matched_count' => $matchedCount,
            'match_count' => $matchedCount,
            'mismatched_count' => $mismatchedCount,
            'mismatch_count' => $mismatchedCount,
            'total_checks_count' => count($employees) * 10,
            'results' => $results,
            'mismatches' => $mismatches,
            'checked_at' => $checkedAt->format('d/m/Y H:i:s'),
            'checked_by' => $userName,
        ];
    }

    /**
     * Run all 10 reconciliation checks for a single employee.
     *
     * @return array<string, mixed>
     */
    public function reconcileEmployee(
        Employee $employee,
        PayrollPeriod $period,
        ?SecurityOvertimeSummary $summary,
        ?Payslip $payslip
    ): array {
        $checks = [];

        // Check 1: Attendance hours = present_days * 8
        $presentDays = (float) ($summary?->present_days ?: $summary?->total_work_days ?: 0);
        $expectedAttHours = $presentDays * 8.0;
        $actualAttHours = (float) ($summary?->attendance_hours ?: $summary?->total_work_hours ?: 0);
        $diffAttHours = round($actualAttHours - $expectedAttHours, 2);
        $checks['attendance_hours'] = [
            'name' => 'Pemeriksaan Jam Hadir (Masuk * 8 Jam)',
            'field' => 'attendance_hours',
            'source_label' => 'Hari Masuk x 8 Jam',
            'source_value' => $expectedAttHours,
            'target_label' => 'Jam Hadir Rekap',
            'target_value' => $actualAttHours,
            'difference' => $diffAttHours,
            'status' => abs($diffAttHours) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffAttHours) < 0.01 ? 'Sesuai' : 'Koreksi jam hadir agar sesuai dengan formula (Hari Masuk x 8 Jam).',
        ];

        // Check 2: Shortage calculation = MAX(0, target_hours - attendance_hours)
        $targetHours = (float) ($summary?->target_hours ?: 0);
        $expectedCurrentShortage = max(0.0, round($targetHours - $actualAttHours, 2));
        $actualCurrentShortage = (float) ($summary?->current_shortage_hours ?: 0);
        $diffCurrentShortage = round($actualCurrentShortage - $expectedCurrentShortage, 2);
        $checks['current_shortage'] = [
            'name' => 'Kekurangan Jam Periode Berjalan',
            'field' => 'current_shortage_hours',
            'source_label' => 'MAX(0, Target - Hadir)',
            'source_value' => $expectedCurrentShortage,
            'target_label' => 'Kurang Jam Rekap',
            'target_value' => $actualCurrentShortage,
            'difference' => $diffCurrentShortage,
            'status' => abs($diffCurrentShortage) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffCurrentShortage) < 0.01 ? 'Sesuai' : 'Kekurangan jam berjalan tidak sesuai dengan formula target dikurangi kehadiran.',
        ];

        // Check 3: Total shortage = current_shortage + previous_shortage
        $prevShortage = (float) ($summary?->previous_shortage_hours ?: 0);
        $expectedTotalShortage = round($actualCurrentShortage + $prevShortage, 2);
        $actualTotalShortage = (float) ($summary?->total_shortage_hours ?: 0);
        $diffTotalShortage = round($actualTotalShortage - $expectedTotalShortage, 2);
        $checks['total_shortage'] = [
            'name' => 'Total Kekurangan Jam',
            'field' => 'total_shortage_hours',
            'source_label' => 'Kurang Jam + Carry Forward',
            'source_value' => $expectedTotalShortage,
            'target_label' => 'Total Kurang Jam',
            'target_value' => $actualTotalShortage,
            'difference' => $diffTotalShortage,
            'status' => abs($diffTotalShortage) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffTotalShortage) < 0.01 ? 'Sesuai' : 'Total kekurangan jam tidak sama dengan penjumlahan kurang jam periode dan carry forward.',
        ];

        // Check 4: Shortage Offset = MIN(total_shortage, regular_overtime_hours)
        $regularGrossOt = (float) ($summary?->regular_overtime_hours > 0 ? $summary?->regular_overtime_hours : (($summary?->gross_overtime_hours) ?: 0));
        $expectedOffset = min($actualTotalShortage, $regularGrossOt);
        $actualOffset = (float) ($summary?->shortage_offset_hours ?: 0);
        $diffOffset = round($actualOffset - $expectedOffset, 2);
        $checks['shortage_offset'] = [
            'name' => 'Kompensasi Jam Lembur (Offset)',
            'field' => 'shortage_offset_hours',
            'source_label' => 'MIN(Total Kurang, Jam Lembur)',
            'source_value' => $expectedOffset,
            'target_label' => 'Offset Tersimpan',
            'target_value' => $actualOffset,
            'difference' => $diffOffset,
            'status' => abs($diffOffset) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffOffset) < 0.01 ? 'Sesuai' : 'Kompensasi jam lembur terhadap kekurangan jam tidak sesuai.',
        ];

        // Check 5: REGULAR Overtime Cost = regular_first_hours_cost + regular_subsequent_hours_cost
        $firstCost = (float) ($summary?->regular_first_hours_cost ?: $summary?->first_hours_cost ?: 0);
        $subCost = (float) ($summary?->regular_subsequent_hours_cost ?: $summary?->subsequent_hours_cost ?: 0);
        $expectedRegCost = round($firstCost + $subCost, 2);
        $actualRegCost = (float) ($summary?->regular_overtime_cost > 0 ? $summary?->regular_overtime_cost : (($summary?->total_overtime_cost) ?: 0));
        $diffRegCost = round($actualRegCost - $expectedRegCost, 2);
        $checks['regular_overtime_cost'] = [
            'name' => 'Biaya Lembur Reguler',
            'field' => 'regular_overtime_cost',
            'source_label' => 'Biaya Jam 1 + Biaya Jam Lanjutan',
            'source_value' => $expectedRegCost,
            'target_label' => 'Biaya Lembur Reguler',
            'target_value' => $actualRegCost,
            'difference' => $diffRegCost,
            'status' => abs($diffRegCost) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffRegCost) < 0.01 ? 'Sesuai' : 'Total biaya lembur reguler tidak cocok dengan rincian jam pertama dan lanjutan.',
        ];

        // Check 6: OFF_HOLIDAY Overtime Cost (Protection verification: 0 if rate unconfirmed)
        $rateConfirmed = (bool) ($summary?->off_holiday_rate_confirmed ?? false);
        $actualOffCost = (float) ($summary?->off_holiday_overtime_cost ?: 0);
        $offOtHours = (float) ($summary?->off_holiday_overtime_hours ?: 0);
        $isOffCostProtected = ! $rateConfirmed ? $actualOffCost === 0.0 : true;
        $checks['off_holiday_protection'] = [
            'name' => 'Proteksi Tarif Lembur Hari Libur',
            'field' => 'off_holiday_overtime_cost',
            'source_label' => $rateConfirmed ? 'Tarif Terkonfirmasi' : 'Tarif Belum Dikonfirmasi (Proteksi Rp 0)',
            'source_value' => $rateConfirmed ? $actualOffCost : 0.0,
            'target_label' => 'Biaya Lembur Libur Masuk Payroll',
            'target_value' => $actualOffCost,
            'difference' => $rateConfirmed ? 0.0 : $actualOffCost,
            'status' => $isOffCostProtected ? 'MATCH' : 'MISMATCH',
            'guidance' => $isOffCostProtected ? 'Sesuai' : 'Biaya lembur hari libur tidak boleh dicairkan sebelum tarif dikonfirmasi tertulis.',
        ];

        // Check 7: Total Overtime Summary vs Payslip OVERTIME Component
        $totalOvertimeSummary = (float) ($summary?->total_overtime_cost ?: 0);
        $overtimeItem = $payslip?->items?->firstWhere('item_code_snapshot', 'OVERTIME');
        $payslipOvertimeAmount = $overtimeItem ? (float) $overtimeItem->amount : 0.0;
        $diffOtItem = round($payslipOvertimeAmount - $totalOvertimeSummary, 2);
        $checks['overtime_component'] = [
            'name' => 'Integritas Komponen Slip Gaji OVERTIME',
            'field' => 'payslip_overtime_component',
            'source_label' => 'Total Biaya Lembur Rekap',
            'source_value' => $totalOvertimeSummary,
            'target_label' => 'Item OVERTIME di Slip Gaji',
            'target_value' => $payslipOvertimeAmount,
            'difference' => $diffOtItem,
            'status' => abs($diffOtItem) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffOtItem) < 0.01 ? 'Sesuai' : 'Nominal lembur di rekap berbeda dengan slip gaji. Silakan lakukan sinkronisasi slip gaji.',
        ];

        // Check 8: Total Earnings in Payslip matches sum of all earning items
        $sumEarningsItems = 0.0;
        $sumDeductionsItems = 0.0;
        if ($payslip && $payslip->items) {
            foreach ($payslip->items as $it) {
                if ($it->item_type === 'earning') {
                    $sumEarningsItems += (float) $it->amount;
                } elseif ($it->item_type === 'deduction') {
                    $sumDeductionsItems += (float) $it->amount;
                }
            }
        }
        $actualEarnings = (float) ($payslip?->total_earnings ?: 0);
        $diffEarnings = round($actualEarnings - $sumEarningsItems, 2);
        $checks['total_earnings'] = [
            'name' => 'Kesesuaian Total Penghasilan',
            'field' => 'total_earnings',
            'source_label' => 'Jumlah Item Penghasilan',
            'source_value' => round($sumEarningsItems, 2),
            'target_label' => 'Total Penghasilan Slip',
            'target_value' => $actualEarnings,
            'difference' => $diffEarnings,
            'status' => abs($diffEarnings) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffEarnings) < 0.01 ? 'Sesuai' : 'Total penghasilan tidak sama dengan jumlah rincian item penghasilan.',
        ];

        // Check 9: Total Deductions in Payslip matches sum of all deduction items
        $actualDeductions = (float) ($payslip?->total_deductions ?: 0);
        $diffDeductions = round($actualDeductions - $sumDeductionsItems, 2);
        $checks['total_deductions'] = [
            'name' => 'Kesesuaian Total Potongan',
            'field' => 'total_deductions',
            'source_label' => 'Jumlah Item Potongan',
            'source_value' => round($sumDeductionsItems, 2),
            'target_label' => 'Total Potongan Slip',
            'target_value' => $actualDeductions,
            'difference' => $diffDeductions,
            'status' => abs($diffDeductions) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffDeductions) < 0.01 ? 'Sesuai' : 'Total potongan tidak sama dengan jumlah rincian item potongan.',
        ];

        // Check 10: Take Home Pay (THP / net_salary) = total_earnings - total_deductions
        $expectedNetSalary = round($actualEarnings - $actualDeductions, 2);
        $actualNetSalary = (float) ($payslip?->net_salary ?: 0);
        $diffNet = round($actualNetSalary - $expectedNetSalary, 2);
        $checks['net_salary'] = [
            'name' => 'Kesesuaian Penghasilan, Potongan, dan THP',
            'field' => 'net_salary',
            'source_label' => 'Penghasilan - Potongan',
            'source_value' => $expectedNetSalary,
            'target_label' => 'Net Salary Slip Gaji',
            'target_value' => $actualNetSalary,
            'difference' => $diffNet,
            'status' => abs($diffNet) < 0.01 ? 'MATCH' : 'MISMATCH',
            'guidance' => abs($diffNet) < 0.01 ? 'Sesuai' : 'Take Home Pay tidak sesuai dengan selisih penghasilan dan potongan.',
        ];

        return [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'department' => $employee->division?->name ?? (is_string($employee->department) ? $employee->department : ($employee->department?->name ?? 'Security')),
            'office' => $employee->office?->name ?? 'Pusat',
            'checks' => array_values($checks),
            'checks_map' => $checks,
        ];
    }
}
