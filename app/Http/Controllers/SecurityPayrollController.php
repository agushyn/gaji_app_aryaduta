<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\SecurityAttendance;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Services\AuditLogService;
use App\Services\SecurityPayrollCalculationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityPayrollController extends Controller
{
    public function __construct(
        protected SecurityPayrollCalculationService $securityService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display the Security Payroll matrix and recap.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.view') && ! $user->hasPermission('payslips.view'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat modul Payroll Security.');
        }

        $periods = PayrollPeriod::orderByDesc('start_date')->get();

        $selectedPeriodId = $request->input('period_id');
        $period = $selectedPeriodId
            ? PayrollPeriod::find($selectedPeriodId)
            : ($periods->first());

        if (! $period) {
            return Inertia::render('SecurityPayroll/Index', [
                'periods' => $periods,
                'currentPeriod' => null,
                'securityEmployees' => [],
                'summaries' => (object) [],
                'activeConfig' => SecurityPayrollConfig::getActiveConfig(),
                'totalOvertimeAllSecurity' => 0,
                'totalEmployees' => 0,
                'canManage' => $user->hasPermission('payroll.security.manage'),
                'canConfig' => $user->hasPermission('payroll.security.config'),
                'filters' => $request->only(['period_id', 'search']),
            ]);
        }

        $employeesQuery = Employee::query()
            ->security()
            ->active()
            ->with(['position', 'office']);

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $employeesQuery->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('employee_number', 'like', "%{$term}%");
            });
        }

        $securityEmployees = $employeesQuery->orderBy('full_name')->get();

        // Load or auto-calculate summaries for all security employees
        $activeConfig = SecurityPayrollConfig::getActiveConfig();
        $summariesMap = [];
        $totalOvertimeAllSecurity = 0.0;

        $existingSummaries = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $securityEmployees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        foreach ($securityEmployees as $emp) {
            $summary = $existingSummaries->get($emp->id);

            if (! $summary && $period->canBeModified()) {
                $summary = $this->securityService->calculateForEmployee($emp, $period, $activeConfig);
            }

            if ($summary) {
                $summariesMap[$emp->id] = [
                    'id' => $summary->id,
                    'present_days' => (int) ($summary->present_days ?: $summary->total_work_days),
                    'off_days' => (int) ($summary->off_days ?: $summary->total_off_days),
                    'sick_days' => (int) $summary->sick_days,
                    'permission_days' => (int) $summary->permission_days,
                    'leave_days' => (int) $summary->leave_days,
                    'absent_days' => (int) ($summary->absent_days ?: $summary->total_absent_days),
                    'total_absence_days' => (int) ($summary->sick_days + $summary->permission_days + $summary->leave_days + ($summary->absent_days ?: $summary->total_absent_days)),
                    'attendance_hours' => (float) ($summary->attendance_hours ?: ($summary->present_days ?: $summary->total_work_days) * 8.0),
                    'target_hours' => (float) $summary->target_hours,
                    'current_shortage_hours' => (float) $summary->current_shortage_hours,
                    'previous_shortage_hours' => (float) $summary->previous_shortage_hours,
                    'total_shortage_hours' => (float) $summary->total_shortage_hours,
                    'shortage_offset_hours' => (float) $summary->shortage_offset_hours,
                    'remaining_shortage_hours' => (float) $summary->remaining_shortage_hours,
                    'regular_overtime_days' => (int) $summary->regular_overtime_days,
                    'regular_overtime_hours' => (float) ($summary->regular_overtime_hours > 0 ? $summary->regular_overtime_hours : $summary->gross_overtime_hours),
                    'regular_break_deduction_hours' => (float) $summary->regular_break_deduction_hours,
                    'regular_net_overtime_hours' => (float) $summary->regular_net_overtime_hours,
                    'regular_first_hours' => (float) $summary->regular_first_hours,
                    'regular_first_hours_cost' => (float) $summary->regular_first_hours_cost,
                    'regular_subsequent_hours' => (float) $summary->regular_subsequent_hours,
                    'regular_subsequent_hours_cost' => (float) $summary->regular_subsequent_hours_cost,
                    'regular_overtime_cost' => (float) $summary->regular_overtime_cost,
                    'off_holiday_overtime_days' => (int) $summary->off_holiday_overtime_days,
                    'off_holiday_overtime_hours' => (float) $summary->off_holiday_overtime_hours,
                    'off_holiday_break_deduction_hours' => (float) $summary->off_holiday_break_deduction_hours,
                    'off_holiday_net_overtime_hours' => (float) $summary->off_holiday_net_overtime_hours,
                    'off_holiday_multiplier' => (float) $summary->off_holiday_multiplier,
                    'off_holiday_overtime_cost' => (float) $summary->off_holiday_overtime_cost,
                    'off_holiday_rate_confirmed' => (bool) $summary->off_holiday_rate_confirmed,
                    'total_days' => $summary->total_days,
                    'total_work_days' => $summary->total_work_days,
                    'total_off_days' => $summary->total_off_days,
                    'total_absent_days' => $summary->total_absent_days,
                    'total_work_hours' => (float) $summary->total_work_hours,
                    'gross_overtime_hours' => (float) $summary->gross_overtime_hours,
                    'break_deduction_hours' => (float) $summary->break_deduction_hours,
                    'net_overtime_hours' => (float) $summary->net_overtime_hours,
                    'hourly_rate' => (float) $summary->hourly_rate,
                    'first_hours_limit' => (float) $summary->first_hours_limit,
                    'first_hours_multiplier' => (float) $summary->first_hours_multiplier,
                    'subsequent_hours_multiplier' => (float) $summary->subsequent_hours_multiplier,
                    'first_hours' => (float) $summary->first_hours,
                    'first_hours_cost' => (float) $summary->first_hours_cost,
                    'subsequent_hours' => (float) $summary->subsequent_hours,
                    'subsequent_hours_cost' => (float) $summary->subsequent_hours_cost,
                    'total_overtime_cost' => (float) $summary->total_overtime_cost,
                    'total_period_salary' => (float) $summary->total_period_salary,
                    'calculation_snapshot' => $summary->calculation_snapshot,
                ];
                $totalOvertimeAllSecurity += (float) $summary->total_overtime_cost;
            }
        }

        return Inertia::render('SecurityPayroll/Index', [
            'periods' => $periods,
            'currentPeriod' => $period,
            'securityEmployees' => $securityEmployees,
            'summaries' => (object) $summariesMap,
            'activeConfig' => $activeConfig,
            'totalOvertimeAllSecurity' => $totalOvertimeAllSecurity,
            'totalEmployees' => $securityEmployees->count(),
            'canManage' => $user->hasPermission('payroll.security.manage') || $user->hasPermission('payslips.manage'),
            'canConfig' => $user->hasPermission('payroll.security.config') || $user->hasPermission('payroll.configuration.update'),
            'filters' => $request->only(['period_id', 'search']),
        ]);
    }

    /**
     * Update a single daily attendance cell.
     */
    public function updateAttendance(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.manage') && ! $user->hasPermission('payslips.manage'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah absensi Security.');
        }

        $validated = $request->validate([
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'string', 'in:present,off,absent,sick,leave'],
            'work_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'overtime_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);
        if (! $period->canBeModified()) {
            throw new DomainException("Tidak dapat mengubah absensi pada periode berstatus '{$period->status_label}'.");
        }

        // Validate date within period
        $attDate = Carbon::parse($validated['date'])->startOfDay();
        $startDate = Carbon::parse($period->start_date)->startOfDay();
        $endDate = Carbon::parse($period->end_date)->startOfDay();

        if ($attDate->lt($startDate) || $attDate->gt($endDate)) {
            return back()->withErrors(['date' => 'Tanggal absensi berada di luar rentang tanggal periode payroll.']);
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        DB::transaction(function () use ($period, $employee, $validated, $user) {
            $att = SecurityAttendance::updateOrCreate(
                [
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'date' => $validated['date'],
                ],
                [
                    'status' => $validated['status'],
                    'work_hours' => $validated['work_hours'],
                    'overtime_hours' => $validated['overtime_hours'],
                    'note' => $validated['note'] ?? null,
                    'updated_by' => $user->id,
                ]
            );

            // Recalculate summary and sync to draft payslip
            $this->securityService->calculateForEmployee($employee, $period);
            $this->securityService->syncSecurityPayslip($period, $employee);

            $this->auditLogService->log(
                action: 'security_attendance.updated',
                subject: $att,
                changes: [
                    'employee' => $employee->full_name,
                    'date' => $validated['date'],
                    'status' => $validated['status'],
                    'work_hours' => $validated['work_hours'],
                    'overtime_hours' => $validated['overtime_hours'],
                ]
            );
        });

        return back()->with('success', "Data absensi {$employee->full_name} tanggal {$validated['date']} berhasil disimpan.");
    }

    /**
     * Update summary working days and overtime hours (simplified input).
     */
    public function updateSummary(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.manage') && ! $user->hasPermission('payslips.manage'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah rekap lembur Security.');
        }

        $validated = $request->validate([
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'present_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'off_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'sick_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'permission_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'leave_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'absent_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'regular_overtime_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'regular_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'off_holiday_overtime_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'off_holiday_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'previous_shortage_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'off_holiday_rate_confirmed' => ['nullable', 'boolean'],
            // Backward-compatible fallback fields
            'total_work_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'gross_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'break_deduction_hours' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);
        if (! $period->canBeModified()) {
            throw new DomainException("Tidak dapat mengubah rekap pada periode berstatus '{$period->status_label}'.");
        }

        $maxDays = count($this->securityService->getPeriodDates($period));

        $presentDays = isset($validated['present_days']) ? (int) $validated['present_days'] : (int) ($validated['total_work_days'] ?? 0);
        $offDays = (int) ($validated['off_days'] ?? 0);
        $sickDays = (int) ($validated['sick_days'] ?? 0);
        $permissionDays = (int) ($validated['permission_days'] ?? 0);
        $leaveDays = (int) ($validated['leave_days'] ?? 0);
        $absentDays = isset($validated['absent_days'])
            ? (int) $validated['absent_days']
            : (! isset($validated['present_days']) && isset($validated['total_work_days'])
                ? max(0, $maxDays - $presentDays - $offDays)
                : 0);

        $totalDaysCount = $presentDays + $offDays + $sickDays + $permissionDays + $leaveDays + $absentDays;
        if ($totalDaysCount > $maxDays) {
            return back()->withErrors([
                'present_days' => "Total hari kehadiran dan ketidakhadiran ({$totalDaysCount} hari) tidak boleh melebihi jumlah hari periode ({$maxDays} hari).",
                'total_work_days' => "Jumlah hari kerja tidak boleh melebihi total hari periode ({$maxDays} hari).",
            ]);
        }

        $regularOtDays = (int) ($validated['regular_overtime_days'] ?? 0);
        $offHolidayOtDays = (int) ($validated['off_holiday_overtime_days'] ?? 0);
        if ($regularOtDays > $maxDays) {
            return back()->withErrors(['regular_overtime_days' => "Jumlah hari lembur reguler ({$regularOtDays} hari) melebihi hari periode ({$maxDays} hari)."]);
        }
        if ($offHolidayOtDays > $maxDays) {
            return back()->withErrors(['off_holiday_overtime_days' => "Jumlah hari lembur OFF/libur ({$offHolidayOtDays} hari) melebihi hari periode ({$maxDays} hari)."]);
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        $overrides = [
            'present_days' => $presentDays,
            'off_days' => $offDays,
            'sick_days' => $sickDays,
            'permission_days' => $permissionDays,
            'leave_days' => $leaveDays,
            'absent_days' => $absentDays,
            'regular_overtime_days' => $regularOtDays,
            'regular_overtime_hours' => isset($validated['regular_overtime_hours']) ? (float) $validated['regular_overtime_hours'] : (float) ($validated['gross_overtime_hours'] ?? 0),
            'off_holiday_overtime_days' => $offHolidayOtDays,
            'off_holiday_overtime_hours' => (float) ($validated['off_holiday_overtime_hours'] ?? 0),
            'total_work_days' => $presentDays,
        ];

        if (isset($validated['gross_overtime_hours']) && ! isset($validated['regular_overtime_hours'])) {
            $overrides['gross_overtime_hours'] = (float) $validated['gross_overtime_hours'];
        }
        if (isset($validated['break_deduction_hours'])) {
            $overrides['break_deduction_hours'] = (float) $validated['break_deduction_hours'];
        }
        if (isset($validated['previous_shortage_hours'])) {
            $overrides['previous_shortage_hours'] = (float) $validated['previous_shortage_hours'];
        }
        if (isset($validated['off_holiday_rate_confirmed'])) {
            $overrides['off_holiday_rate_confirmed'] = (bool) $validated['off_holiday_rate_confirmed'];
        }

        DB::transaction(function () use ($period, $employee, $overrides) {
            $this->securityService->calculateForEmployee($employee, $period, null, $overrides);
            $this->securityService->syncSecurityPayslip($period, $employee);
        });

        return back()->with('success', "Rekap kehadiran dan lembur {$employee->full_name} berhasil diperbarui.");
    }

    /**
     * Bulk update summary attendance and overtime for multiple employees.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.manage') && ! $user->hasPermission('payslips.manage'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola rekap payroll Security.');
        }

        $validated = $request->validate([
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.employee_id' => ['required', 'exists:employees,id'],
            'items.*.present_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.off_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.sick_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.permission_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.leave_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.absent_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.regular_overtime_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.regular_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'items.*.off_holiday_overtime_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'items.*.off_holiday_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'items.*.previous_shortage_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'items.*.off_holiday_rate_confirmed' => ['nullable', 'boolean'],
        ]);

        $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);
        if (! $period->canBeModified()) {
            throw new DomainException("Tidak dapat mengubah rekap pada periode berstatus '{$period->status_label}'.");
        }

        $maxDays = count($this->securityService->getPeriodDates($period));
        $errors = [];

        foreach ($validated['items'] as $index => $item) {
            $present = (int) ($item['present_days'] ?? 0);
            $off = (int) ($item['off_days'] ?? 0);
            $sick = (int) ($item['sick_days'] ?? 0);
            $permission = (int) ($item['permission_days'] ?? 0);
            $leave = (int) ($item['leave_days'] ?? 0);
            $absent = (int) ($item['absent_days'] ?? 0);
            $totalDays = $present + $off + $sick + $permission + $leave + $absent;

            if ($totalDays > $maxDays) {
                $errors["items.{$index}.present_days"] = "Total hari ({$totalDays} hari) melebihi batas periode ({$maxDays} hari).";
            }

            $regDays = (int) ($item['regular_overtime_days'] ?? 0);
            $offDaysOt = (int) ($item['off_holiday_overtime_days'] ?? 0);
            if ($regDays > $maxDays) {
                $errors["items.{$index}.regular_overtime_days"] = "Hari lembur reguler ({$regDays}) melebihi hari periode.";
            }
            if ($offDaysOt > $maxDays) {
                $errors["items.{$index}.off_holiday_overtime_days"] = "Hari lembur OFF/libur ({$offDaysOt}) melebihi hari periode.";
            }
        }

        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        $count = 0;
        DB::transaction(function () use ($period, $validated, &$count) {
            foreach ($validated['items'] as $item) {
                $employee = Employee::find($item['employee_id']);
                if (! $employee) {
                    continue;
                }

                $presentDays = (int) ($item['present_days'] ?? 0);
                $overrides = [
                    'present_days' => $presentDays,
                    'off_days' => (int) ($item['off_days'] ?? 0),
                    'sick_days' => (int) ($item['sick_days'] ?? 0),
                    'permission_days' => (int) ($item['permission_days'] ?? 0),
                    'leave_days' => (int) ($item['leave_days'] ?? 0),
                    'absent_days' => (int) ($item['absent_days'] ?? 0),
                    'regular_overtime_days' => (int) ($item['regular_overtime_days'] ?? 0),
                    'regular_overtime_hours' => (float) ($item['regular_overtime_hours'] ?? 0),
                    'off_holiday_overtime_days' => (int) ($item['off_holiday_overtime_days'] ?? 0),
                    'off_holiday_overtime_hours' => (float) ($item['off_holiday_overtime_hours'] ?? 0),
                    'total_work_days' => $presentDays,
                ];

                if (isset($item['previous_shortage_hours'])) {
                    $overrides['previous_shortage_hours'] = (float) $item['previous_shortage_hours'];
                }
                if (isset($item['off_holiday_rate_confirmed'])) {
                    $overrides['off_holiday_rate_confirmed'] = (bool) $item['off_holiday_rate_confirmed'];
                }

                $this->securityService->calculateForEmployee($employee, $period, null, $overrides);
                $this->securityService->syncSecurityPayslip($period, $employee);
                $count++;
            }

            $this->auditLogService->log(
                action: 'security_payroll.bulk_updated',
                subject: $period,
                changes: [
                    'count' => $count,
                    'period_code' => $period->period_code,
                ]
            );
        });

        return back()->with('success', "Rekap kehadiran dan lembur {$count} petugas Security berhasil disimpan.");
    }

    /**
     * Preview calculation for an employee before saving or finalizing.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.view') && ! $user->hasPermission('payslips.view'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat kalkulasi Security.');
        }

        $validated = $request->validate([
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'present_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'off_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'sick_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'permission_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'leave_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'absent_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'regular_overtime_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'regular_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'off_holiday_overtime_days' => ['nullable', 'integer', 'min:0', 'max:62'],
            'off_holiday_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'previous_shortage_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'off_holiday_rate_confirmed' => ['nullable', 'boolean'],
        ]);

        $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);
        $employee = Employee::findOrFail($validated['employee_id']);

        $preview = $this->securityService->previewForEmployee($employee, $period, null, $validated);

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Update formula configuration.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.config') && ! $user->hasPermission('payroll.configuration.update'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah konfigurasi rumus Security.');
        }

        $validated = $request->validate([
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'first_hours_limit' => ['required', 'numeric', 'min:0', 'max:50'],
            'first_hours_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
            'subsequent_hours_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
            'break_deduction_default' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'max_break_deduction' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'round_rule' => ['required', 'string', 'in:round,floor,ceil'],
        ]);

        $config = SecurityPayrollConfig::getActiveConfig();

        DB::transaction(function () use ($config, $validated, $user) {
            $oldData = $config->toArray();

            $config->update([
                'hourly_rate' => $validated['hourly_rate'],
                'first_hours_limit' => $validated['first_hours_limit'],
                'first_hours_multiplier' => $validated['first_hours_multiplier'],
                'subsequent_hours_multiplier' => $validated['subsequent_hours_multiplier'],
                'break_deduction_default' => $validated['break_deduction_default'] ?? 0,
                'max_break_deduction' => $validated['max_break_deduction'] ?? 20,
                'round_rule' => $validated['round_rule'],
                'version' => $config->version + 1,
                'updated_by' => $user->id,
            ]);

            $this->auditLogService->log(
                action: 'security_payroll_config.updated',
                subject: $config,
                changes: [
                    'version' => $config->version,
                    'old' => $oldData,
                    'new' => $config->toArray(),
                ]
            );
        });

        return back()->with('success', 'Konfigurasi rumus lembur Security berhasil diperbarui ke versi '.$config->version);
    }

    /**
     * Sync all security payslips for the selected period.
     */
    public function syncPayslips(PayrollPeriod $period): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('payroll.security.manage') && ! $user->hasPermission('payslips.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk sinkronisasi slip gaji Security.');
        }

        if (! $period->canBeModified()) {
            throw new DomainException("Tidak dapat sinkronisasi slip pada periode dengan status '{$period->status_label}'.");
        }

        $securityEmployees = Employee::security()->active()->get();
        $synced = 0;

        DB::transaction(function () use ($period, $securityEmployees, &$synced) {
            foreach ($securityEmployees as $emp) {
                $this->securityService->calculateForEmployee($emp, $period);
                $this->securityService->syncSecurityPayslip($period, $emp);
                $synced++;
            }

            $this->auditLogService->log(
                action: 'security_payroll.synced',
                subject: $period,
                changes: [
                    'count' => $synced,
                    'period_code' => $period->period_code,
                ]
            );
        });

        return back()->with('success', "{$synced} slip gaji Security berhasil disinkronisasi.");
    }

    /**
     * Export working days, overtime recap to Excel.
     */
    public function exportExcel(PayrollPeriod $period): StreamedResponse
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('payroll.security.view') && ! $user->hasPermission('reports.export'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh rekap payroll Security.');
        }

        $securityEmployees = Employee::security()->active()->orderBy('full_name')->get();
        $employeeIds = $securityEmployees->pluck('id')->toArray();

        $summaries = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Lembur Security');

        // Header Title
        $sheet->setCellValue('A1', 'REKAPITULASI HARI KERJA DAN LEMBUR SECURITY');
        $sheet->setCellValue('A2', 'PERIODE: '.Carbon::parse($period->start_date)->translatedFormat('d F Y').' s/d '.Carbon::parse($period->end_date)->translatedFormat('d F Y'));
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);

        // Build Table Headers
        $row = 4;
        $sheet->setCellValue('A'.$row, 'No.');
        $sheet->setCellValue('B'.$row, 'Nama Security');

        $summaryHeaders = [
            'Total Hari',
            'Hari Kerja',
            'Jam Kerja',
            'Jam Lembur',
            'Potongan Istirahat',
            'Jam Lembur Bersih',
            'Upah / Jam',
            'Jam Pertama',
            'Biaya Jam Pertama',
            'Jam Berikutnya',
            'Biaya Jam Berikutnya',
            'Total Biaya Lembur',
            'Total Upah Periode',
        ];

        $col = 3; // Column C
        foreach ($summaryHeaders as $h) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($colLetter.$row, $h);
            $sheet->getStyle($colLetter.$row)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        $maxCol = $col - 1;
        $maxColLetter = Coordinate::stringFromColumnIndex($maxCol);
        $headerRange = 'A'.$row.':'.$maxColLetter.$row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        // Data Rows
        $dataRow = 5;
        $no = 1;
        $totalOvertimeSum = 0;

        foreach ($securityEmployees as $emp) {
            $sheet->setCellValue('A'.$dataRow, $no++);
            $sheet->setCellValue('B'.$dataRow, $emp->full_name);

            $sum = $summaries->get($emp->id);
            if (! $sum) {
                $sum = $this->securityService->calculateForEmployee($emp, $period);
            }

            $summaryValues = [
                $sum->total_days,
                $sum->total_work_days,
                $sum->total_work_hours,
                $sum->gross_overtime_hours,
                $sum->break_deduction_hours,
                $sum->net_overtime_hours,
                $sum->hourly_rate,
                $sum->first_hours,
                $sum->first_hours_cost,
                $sum->subsequent_hours,
                $sum->subsequent_hours_cost,
                $sum->total_overtime_cost,
                $sum->total_period_salary,
            ];

            $col = 3;
            foreach ($summaryValues as $idx => $sVal) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue($colLetter.$dataRow, $sVal);

                if (in_array($idx, [6, 8, 10, 11, 12], true)) {
                    $sheet->getStyle($colLetter.$dataRow)->getNumberFormat()->setFormatCode('#,##0');
                }
                $col++;
            }

            $totalOvertimeSum += (float) $sum->total_overtime_cost;
            $dataRow++;
        }

        // Footer Total Row
        $footerRow = $dataRow;
        $sheet->setCellValue('A'.$footerRow, 'TOTAL LEMBUR SECURITY');
        // Total Biaya Lembur is the 12th summary column starting at C
        $costColIndex = 3 + 11;
        $costColLetter = Coordinate::stringFromColumnIndex($costColIndex);
        $beforeCostLetter = Coordinate::stringFromColumnIndex($costColIndex - 1);

        $sheet->mergeCells('A'.$footerRow.':'.$beforeCostLetter.$footerRow);
        $sheet->setCellValue($costColLetter.$footerRow, $totalOvertimeSum);
        $sheet->getStyle($costColLetter.$footerRow)->getNumberFormat()->setFormatCode('#,##0');

        $footerRange = 'A'.$footerRow.':'.$maxColLetter.$footerRow;
        $sheet->getStyle($footerRange)->getFont()->setBold(true);
        $sheet->getStyle($footerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');

        // Borders
        $fullTableRange = 'A4:'.$maxColLetter.$footerRow;
        $sheet->getStyle($fullTableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $writer = new Xlsx($spreadsheet);
        $filename = "Rekap_Lembur_Security_{$period->period_code}.xlsx";

        return response()->stream(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}
