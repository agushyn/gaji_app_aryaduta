<?php

namespace App\Services\Security;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollApprovalEvent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SecurityOvertimeSummary;
use App\Services\SecurityPayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SecurityPayrollReportService
{
    public function __construct(
        protected SecurityPayrollCalculationService $calculationService
    ) {}

    /**
     * Get aggregated KPI dashboard metrics for a security payroll period.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(PayrollPeriod $period, array $filters = []): array
    {
        $employeesQuery = $this->buildBaseEmployeeQuery($filters);
        $employees = $employeesQuery->get();
        $employeeIds = $employees->pluck('id')->toArray();
        $totalEmployees = count($employeeIds);

        // Fetch summaries for this period
        $summaries = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get();

        // Fetch payslips for this period
        $payslips = Payslip::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get();

        $draftCount = 0;
        $submittedCount = 0;
        $approvedCount = 0;
        $finalizedCount = 0;
        $totalEarnings = '0.00';
        $totalDeductions = '0.00';
        $totalNetSalary = '0.00';

        foreach ($payslips as $p) {
            if ($p->status === PayslipStatus::DRAFT->value) {
                $draftCount++;
            } elseif ($p->status === PayslipStatus::PENDING_REVIEW->value) {
                $submittedCount++;
            } elseif ($p->status === PayslipStatus::APPROVED->value) {
                $approvedCount++;
            } elseif ($p->status === PayslipStatus::FINALIZED->value) {
                $finalizedCount++;
            }

            $totalEarnings = bcadd($totalEarnings, (string) ($p->total_earnings ?? 0), 2);
            $totalDeductions = bcadd($totalDeductions, (string) ($p->total_deductions ?? 0), 2);
            $totalNetSalary = bcadd($totalNetSalary, (string) ($p->net_salary ?? 0), 2);
        }

        $totalRegularOtHours = 0.0;
        $totalRegularOtCost = '0.00';
        $totalOffHolidayOtHours = 0.0;
        $totalOffHolidayOtCost = '0.00';
        $shortageCount = 0;
        $incompleteCount = 0;

        $periodDays = count($this->calculationService->getPeriodDates($period));

        foreach ($employees as $emp) {
            $sum = $summaries->firstWhere('employee_id', $emp->id);
            if (! $sum) {
                $incompleteCount++;

                continue;
            }

            $regNet = (float) $sum->regular_net_overtime_hours;
            $regCost = (string) $sum->regular_overtime_cost;
            $offNet = (float) $sum->off_holiday_net_overtime_hours;
            $offCost = (string) $sum->off_holiday_overtime_cost;

            $totalRegularOtHours += $regNet;
            $totalRegularOtCost = bcadd($totalRegularOtCost, $regCost, 2);
            $totalOffHolidayOtHours += $offNet;
            $totalOffHolidayOtCost = bcadd($totalOffHolidayOtCost, $offCost, 2);

            if ((float) $sum->total_shortage_hours > 0) {
                $shortageCount++;
            }

            $totalDays = (int) ($sum->present_days ?: $sum->total_work_days)
                + (int) ($sum->off_days ?: $sum->total_off_days)
                + (int) $sum->sick_days
                + (int) $sum->permission_days
                + (int) $sum->leave_days
                + (int) ($sum->absent_days ?: $sum->total_absent_days);

            if ($totalDays > $periodDays) {
                $incompleteCount++;
            }
        }

        $totalOvertimeCost = bcadd($totalRegularOtCost, $totalOffHolidayOtCost, 2);

        return [
            'period' => [
                'id' => $period->id,
                'period_code' => $period->period_code,
                'status' => $period->status,
                'status_label' => $period->status_label,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date' => $period->end_date?->format('Y-m-d'),
                'period_days' => $periodDays,
            ],
            'total_security_employees' => $totalEmployees,
            'draft_count' => $draftCount,
            'submitted_count' => $submittedCount,
            'approved_count' => $approvedCount,
            'finalized_count' => $finalizedCount,
            'total_earnings' => (float) $totalEarnings,
            'total_deductions' => (float) $totalDeductions,
            'total_net_salary' => (float) $totalNetSalary,
            'total_regular_overtime_hours' => round($totalRegularOtHours, 2),
            'total_regular_overtime_cost' => (float) $totalRegularOtCost,
            'total_off_holiday_overtime_hours' => round($totalOffHolidayOtHours, 2),
            'total_off_holiday_overtime_cost' => (float) $totalOffHolidayOtCost,
            'total_overtime_cost' => (float) $totalOvertimeCost,
            'employees_with_shortage_count' => $shortageCount,
            'incomplete_or_issue_count' => $incompleteCount,
        ];
    }

    /**
     * Get period summary report across all periods.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getPeriodSummaryReport(array $filters = []): array
    {
        $periodsQuery = PayrollPeriod::orderByDesc('start_date');

        if (! empty($filters['period_id']) && $filters['period_id'] !== 'all') {
            $periodsQuery->where('id', $filters['period_id']);
        }

        $periods = $periodsQuery->get();
        $report = [];

        foreach ($periods as $p) {
            $metrics = $this->getDashboardMetrics($p, $filters);
            $report[] = [
                'period_id' => $p->id,
                'period_code' => $p->period_code,
                'start_date' => $p->start_date?->format('d/m/Y'),
                'end_date' => $p->end_date?->format('d/m/Y'),
                'period_days' => $metrics['period']['period_days'],
                'status' => $p->status,
                'status_label' => $p->status_label,
                'total_employees' => $metrics['total_security_employees'],
                'total_regular_overtime_hours' => $metrics['total_regular_overtime_hours'],
                'total_regular_overtime_cost' => $metrics['total_regular_overtime_cost'],
                'total_off_holiday_overtime_hours' => $metrics['total_off_holiday_overtime_hours'],
                'total_off_holiday_overtime_cost' => $metrics['total_off_holiday_overtime_cost'],
                'total_overtime_cost' => $metrics['total_overtime_cost'],
                'total_earnings' => $metrics['total_earnings'],
                'total_deductions' => $metrics['total_deductions'],
                'total_net_salary' => $metrics['total_net_salary'],
                'shortage_count' => $metrics['employees_with_shortage_count'],
            ];
        }

        return $report;
    }

    /**
     * Get detailed report for each security employee in a period.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getEmployeeDetailReport(PayrollPeriod $period, array $filters = []): array
    {
        $employees = $this->buildBaseEmployeeQuery($filters)->get();
        $employeeIds = $employees->pluck('id')->toArray();

        $summaries = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $payslips = Payslip::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $rows = [];

        foreach ($employees as $emp) {
            $sum = $summaries->get($emp->id);
            $slip = $payslips->get($emp->id);

            $presentDays = (int) ($sum?->present_days ?: $sum?->total_work_days ?: 0);
            $offDays = (int) ($sum?->off_days ?: $sum?->total_off_days ?: 0);
            $sickDays = (int) ($sum?->sick_days ?: 0);
            $permissionDays = (int) ($sum?->permission_days ?: 0);
            $leaveDays = (int) ($sum?->leave_days ?: 0);
            $absentDays = (int) ($sum?->absent_days ?: $sum?->total_absent_days ?: 0);

            $attHours = (float) ($sum?->attendance_hours ?: $presentDays * 8.0);
            $targetHours = (float) ($sum?->target_hours ?: 0);
            $currentShortage = (float) ($sum?->current_shortage_hours ?: 0);
            $prevShortage = (float) ($sum?->previous_shortage_hours ?: 0);
            $totalShortage = (float) ($sum?->total_shortage_hours ?: 0);

            $regDays = (int) ($sum?->regular_overtime_days ?: 0);
            $regHours = (float) ($sum?->regular_overtime_hours > 0 ? $sum?->regular_overtime_hours : (($sum?->gross_overtime_hours) ?: 0));
            $regNet = (float) ($sum?->regular_net_overtime_hours ?: 0);
            $regCost = (float) ($sum?->regular_overtime_cost ?: 0);

            $offDaysOt = (int) ($sum?->off_holiday_overtime_days ?: 0);
            $offHours = (float) ($sum?->off_holiday_overtime_hours ?: 0);
            $offNet = (float) ($sum?->off_holiday_net_overtime_hours ?: 0);
            $offCost = (float) ($sum?->off_holiday_overtime_cost ?: 0);
            $offRateConfirmed = (bool) ($sum?->off_holiday_rate_confirmed ?? false);

            $totalEarnings = (float) ($slip?->total_earnings ?: 0);
            $totalDeductions = (float) ($slip?->total_deductions ?: 0);
            $netSalary = (float) ($slip?->net_salary ?: 0);

            $rows[] = [
                'employee_id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'employee_name' => $emp->full_name,
                'office' => $emp->office?->name ?? 'Pusat',
                'department' => $emp->division?->name ?? (is_string($emp->department) ? $emp->department : ($emp->department?->name ?? 'Security')),
                'present_days' => $presentDays,
                'off_days' => $offDays,
                'sick_days' => $sickDays,
                'permission_days' => $permissionDays,
                'leave_days' => $leaveDays,
                'absent_days' => $absentDays,
                'attendance_hours' => $attHours,
                'target_hours' => $targetHours,
                'current_shortage' => $currentShortage,
                'previous_shortage' => $prevShortage,
                'total_shortage' => $totalShortage,
                'regular_overtime_days' => $regDays,
                'regular_overtime_hours' => $regHours,
                'regular_net_overtime_hours' => $regNet,
                'regular_overtime_amount' => $regCost,
                'off_holiday_overtime_days' => $offDaysOt,
                'off_holiday_overtime_hours' => $offHours,
                'off_holiday_net_overtime_hours' => $offNet,
                'off_holiday_overtime_amount' => $offCost,
                'off_holiday_rate_confirmed' => $offRateConfirmed,
                'total_overtime_amount' => (float) ($sum?->total_overtime_cost ?: 0),
                'total_earnings' => $totalEarnings,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'payslip_status' => $slip?->status ?? 'none',
                'payslip_status_label' => $slip?->status_label ?? 'Belum Dibuat',
            ];
        }

        return $rows;
    }

    /**
     * Get shortage and carry-forward tracking report for a period.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getShortageReport(PayrollPeriod $period, array $filters = []): array
    {
        $allDetails = $this->getEmployeeDetailReport($period, $filters);
        $statusFilter = strtolower((string) ($filters['status'] ?? 'all'));
        $shortageRows = [];

        $empIds = array_column($allDetails, 'employee_id');
        $summaries = SecurityOvertimeSummary::with('previousPayrollPeriod')
            ->where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id');

        foreach ($allDetails as $d) {
            $sum = $summaries->get($d['employee_id']);

            $prevPeriod = $sum?->previousPayrollPeriod;
            $shortageOffset = (float) ($sum?->shortage_offset_hours ?: 0);
            $totalShortage = (float) ($d['total_shortage'] ?: 0);
            $remainingShortage = max(0.0, round($totalShortage - $shortageOffset, 2));
            $isUnsettled = $remainingShortage > 0;

            if ($statusFilter === 'shortage' && ! $isUnsettled) {
                continue;
            }
            if ($statusFilter === 'clear' && $isUnsettled) {
                continue;
            }

            $shortageRows[] = [
                'employee_id' => $d['employee_id'],
                'employee_number' => $d['employee_number'],
                'employee_name' => $d['employee_name'],
                'office' => $d['office'],
                'period_code' => $period->period_code,
                'target_hours' => $d['target_hours'],
                'attendance_hours' => $d['attendance_hours'],
                'current_shortage' => $d['current_shortage'],
                'previous_shortage' => $d['previous_shortage'],
                'source_period' => $prevPeriod ? $prevPeriod->period_code : ($d['previous_shortage'] > 0 ? 'Input Manual' : '-'),
                'total_shortage' => $totalShortage,
                'shortage_offset' => $shortageOffset,
                'remaining_shortage' => $remainingShortage,
                'status' => $isUnsettled ? 'KURANG JAM' : 'TUNTAS',
            ];
        }

        return $shortageRows;
    }

    /**
     * Get overtime breakdown report filtered by category (REGULAR, OFF_HOLIDAY, or ALL).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getOvertimeReport(PayrollPeriod $period, array $filters = []): array
    {
        $allDetails = $this->getEmployeeDetailReport($period, $filters);
        $categoryFilter = strtolower((string) ($filters['overtime_category'] ?? $filters['category'] ?? 'all'));
        $overtimeRows = [];

        $empIds = array_column($allDetails, 'employee_id');
        $summaries = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id');

        foreach ($allDetails as $d) {
            $sum = $summaries->get($d['employee_id']);

            $regHourlyRate = (float) ($sum?->regular_hourly_rate ?: $sum?->hourly_rate ?: 0);
            $offHourlyRate = (float) ($sum?->off_holiday_hourly_rate ?: $sum?->hourly_rate ?: 0);

            // REGULAR Overtime entry
            if ($categoryFilter === 'all' || $categoryFilter === 'regular') {
                if ($d['regular_overtime_hours'] > 0 || $d['regular_overtime_amount'] > 0) {
                    $overtimeRows[] = [
                        'employee_id' => $d['employee_id'],
                        'employee_number' => $d['employee_number'],
                        'employee_name' => $d['employee_name'],
                        'office' => $d['office'],
                        'period_code' => $period->period_code,
                        'category' => 'REGULAR',
                        'category_label' => 'Lembur Reguler',
                        'overtime_days' => $d['regular_overtime_days'],
                        'input_hours' => $d['regular_overtime_hours'],
                        'break_deduction' => (float) ($sum?->regular_break_deduction_hours ?: 0),
                        'net_hours' => $d['regular_net_overtime_hours'],
                        'hourly_rate' => $regHourlyRate,
                        'amount' => $d['regular_overtime_amount'],
                        'status' => 'TERVERIFIKASI',
                    ];
                }
            }

            // OFF_HOLIDAY Overtime entry
            if ($categoryFilter === 'all' || $categoryFilter === 'off_holiday') {
                if ($d['off_holiday_overtime_hours'] > 0 || $d['off_holiday_overtime_amount'] > 0) {
                    $overtimeRows[] = [
                        'employee_id' => $d['employee_id'],
                        'employee_number' => $d['employee_number'],
                        'employee_name' => $d['employee_name'],
                        'office' => $d['office'],
                        'period_code' => $period->period_code,
                        'category' => 'OFF_HOLIDAY',
                        'category_label' => 'Lembur Hari Libur / OFF',
                        'overtime_days' => $d['off_holiday_overtime_days'],
                        'input_hours' => $d['off_holiday_overtime_hours'],
                        'break_deduction' => (float) ($sum?->off_holiday_break_deduction_hours ?: 0),
                        'net_hours' => $d['off_holiday_net_overtime_hours'],
                        'hourly_rate' => $offHourlyRate,
                        'amount' => $d['off_holiday_overtime_amount'],
                        'status' => $d['off_holiday_rate_confirmed'] ? 'TERVERIFIKASI' : 'PENDING_KONFIRMASI',
                    ];
                }
            }
        }

        return $overtimeRows;
    }

    /**
     * Get unified audit trail history for security payroll in a period.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getAuditHistoryReport(PayrollPeriod $period, array $filters = []): array
    {
        $auditLogs = AuditLog::query()
            ->where(function ($q) use ($period) {
                $q->where(function ($sq) use ($period) {
                    $sq->where('subject_type', PayrollPeriod::class)
                        ->where('subject_id', $period->id);
                })
                ->orWhere(function ($sq) {
                    $sq->where('action', 'like', 'security_%');
                });
            })
            ->latest('created_at')
            ->limit(100)
            ->get();

        $approvalEvents = PayrollApprovalEvent::where('payroll_period_id', $period->id)
            ->with('actor')
            ->latest('created_at')
            ->get();

        $events = [];

        foreach ($approvalEvents as $ae) {
            $events[] = [
                'type' => 'approval_event',
                'action' => 'approval.'.$ae->decision,
                'action_label' => 'Workflow Approval: '.strtoupper($ae->decision_label),
                'actor_name' => $ae->actor?->name ?? 'Sistem',
                'comment' => $ae->comment,
                'created_at' => $ae->created_at?->format('d/m/Y H:i:s'),
                'raw_timestamp' => $ae->created_at?->timestamp ?? 0,
                'details' => [
                    'decision' => $ae->decision,
                    'payslip_id' => $ae->payslip_id,
                ],
            ];
        }

        foreach ($auditLogs as $al) {
            $actionLabel = match ($al->action) {
                'security_attendance.updated' => 'Update Absensi Security',
                'security_payroll.bulk_updated' => 'Bulk Update Rekap Security',
                'security_payroll.synced' => 'Sinkronisasi Slip Gaji Security',
                'security_payroll_config.updated' => 'Perubahan Konfigurasi Rumus Security',
                'payroll_period.auto_generated' => 'Auto Generate Payroll Periode',
                'payroll_period.finalized' => 'Finalisasi Periode Payroll',
                'security_payroll.reconciliation_run' => 'Eksekusi Rekonsiliasi Payroll Security',
                'security_payroll.exported' => 'Export Data Payroll Security',
                default => $al->action,
            };

            $events[] = [
                'type' => 'audit_log',
                'action' => $al->action,
                'action_label' => $actionLabel,
                'actor_name' => $al->user?->name ?? 'Sistem',
                'comment' => is_array($al->changes) ? json_encode($al->changes, JSON_UNESCAPED_UNICODE) : (string) $al->changes,
                'created_at' => $al->created_at?->format('d/m/Y H:i:s'),
                'raw_timestamp' => $al->created_at?->timestamp ?? 0,
                'details' => $al->changes ?? [],
            ];
        }

        // Sort by timestamp descending
        usort($events, fn ($a, $b) => $b['raw_timestamp'] <=> $a['raw_timestamp']);

        return $events;
    }

    /**
     * Helper to build base Employee query for security employees with standard filters.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function buildBaseEmployeeQuery(array $filters)
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

        if (! empty($filters['department']) && $filters['department'] !== 'all') {
            $dept = $filters['department'];
            $query->where(function ($q) use ($dept) {
                $q->where('department', $dept)
                    ->orWhereHas('division', fn ($dq) => $dq->where('name', $dept));
            });
        }

        return $query->orderBy('full_name');
    }
}
