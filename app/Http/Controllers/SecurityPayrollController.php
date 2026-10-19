<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\SecurityAttendance;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Services\AuditLogService;
use App\Services\PayrollExportService;
use App\Services\SecurityPayrollCalculationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
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
                'periodDates' => [],
                'securityEmployees' => [],
                'attendances' => (object) [],
                'summaries' => (object) [],
                'activeConfig' => SecurityPayrollConfig::getActiveConfig(),
                'totalOvertimeAllSecurity' => 0,
                'totalEmployees' => 0,
                'canManage' => $user->hasPermission('payroll.security.manage'),
                'canConfig' => $user->hasPermission('payroll.security.config'),
                'filters' => $request->only(['period_id', 'search']),
            ]);
        }

        $periodDates = collect($this->securityService->getPeriodDates($period))->map(function (Carbon $date) {
            $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

            return [
                'date' => $date->format('Y-m-d'),
                'day_name' => $dayNames[$date->dayOfWeek],
                'formatted_date' => $date->format('d/m'),
                'full_date' => $date->format('d M Y'),
                'is_sunday' => $date->isSunday(),
            ];
        });

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
        $employeeIds = $securityEmployees->pluck('id')->toArray();

        // Load attendances keyed by "employeeId_date"
        $attendancesList = SecurityAttendance::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get();

        $attendancesMap = [];
        foreach ($attendancesList as $att) {
            $dateStr = $att->date instanceof Carbon ? $att->date->format('Y-m-d') : Carbon::parse($att->date)->format('Y-m-d');
            $attendancesMap[$att->employee_id.'_'.$dateStr] = [
                'id' => $att->id,
                'status' => $att->status,
                'work_hours' => (float) $att->work_hours,
                'overtime_hours' => (float) $att->overtime_hours,
                'note' => $att->note,
            ];
        }

        // Load or auto-calculate summaries for all security employees
        $activeConfig = SecurityPayrollConfig::getActiveConfig();
        $summariesMap = [];
        $totalOvertimeAllSecurity = 0.0;

        foreach ($securityEmployees as $emp) {
            $summary = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
                ->where('employee_id', $emp->id)
                ->first();

            if (! $summary && $period->canBeModified()) {
                $summary = $this->securityService->calculateForEmployee($emp, $period, $activeConfig);
            }

            if ($summary) {
                $summariesMap[$emp->id] = [
                    'id' => $summary->id,
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
                ];
                $totalOvertimeAllSecurity += (float) $summary->total_overtime_cost;
            }
        }

        return Inertia::render('SecurityPayroll/Index', [
            'periods' => $periods,
            'currentPeriod' => $period,
            'periodDates' => $periodDates,
            'securityEmployees' => $securityEmployees,
            'attendances' => (object) $attendancesMap,
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
     * Update summary overtime hours or break deduction.
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
            'gross_overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'break_deduction_hours' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);
        if (! $period->canBeModified()) {
            throw new DomainException("Tidak dapat mengubah rekap pada periode berstatus '{$period->status_label}'.");
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        $overrides = [];
        if (isset($validated['gross_overtime_hours'])) {
            $overrides['gross_overtime_hours'] = (float) $validated['gross_overtime_hours'];
        }
        if (isset($validated['break_deduction_hours'])) {
            $overrides['break_deduction_hours'] = (float) $validated['break_deduction_hours'];
        }

        DB::transaction(function () use ($period, $employee, $overrides) {
            $this->securityService->calculateForEmployee($employee, $period, null, $overrides);
            $this->securityService->syncSecurityPayslip($period, $employee);
        });

        return back()->with('success', "Rekap lembur {$employee->full_name} berhasil diperbarui.");
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
        });

        return back()->with('success', "{$synced} slip gaji Security berhasil disinkronisasi.");
    }

    /**
     * Export attendance matrix and overtime recap to Excel.
     */
    public function exportExcel(PayrollPeriod $period): StreamedResponse
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('payroll.security.view') && ! $user->hasPermission('reports.export'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh rekap payroll Security.');
        }

        $dates = $this->securityService->getPeriodDates($period);
        $securityEmployees = Employee::security()->active()->orderBy('full_name')->get();
        $employeeIds = $securityEmployees->pluck('id')->toArray();

        $attendances = SecurityAttendance::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get();

        $attMap = [];
        foreach ($attendances as $att) {
            $d = $att->date instanceof Carbon ? $att->date->format('Y-m-d') : Carbon::parse($att->date)->format('Y-m-d');
            $attMap[$att->employee_id.'_'.$d] = $att;
        }

        $summaries = SecurityOvertimeSummary::where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Lembur Security');

        // Header Title
        $sheet->setCellValue('A1', 'REKAPITULASI KEHADIRAN DAN LEMBUR SECURITY');
        $sheet->setCellValue('A2', 'PERIODE: ' . Carbon::parse($period->start_date)->translatedFormat('d F Y') . ' s/d ' . Carbon::parse($period->end_date)->translatedFormat('d F Y'));
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);

        // Build Table Headers
        $row = 4;
        $sheet->setCellValue('A' . $row, 'No.');
        $sheet->setCellValue('B' . $row, 'Nama Security');

        $col = 3; // Column C
        foreach ($dates as $d) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            $sheet->setCellValue($colLetter . $row, $dayNames[$d->dayOfWeek] . "\n" . $d->format('d/m'));
            $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        $summaryHeaders = [
            'Total Hari',
            'Jam Kerja',
            'OFF',
            'Absen',
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

        foreach ($summaryHeaders as $h) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($colLetter . $row, $h);
            $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        $maxCol = $col - 1;
        $maxColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCol);
        $headerRange = 'A' . $row . ':' . $maxColLetter . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        // Data Rows
        $dataRow = 5;
        $no = 1;
        $totalOvertimeSum = 0;

        foreach ($securityEmployees as $emp) {
            $sheet->setCellValue('A' . $dataRow, $no++);
            $sheet->setCellValue('B' . $dataRow, $emp->full_name);

            $col = 3;
            foreach ($dates as $d) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $key = $emp->id . '_' . $d->format('Y-m-d');
                $att = $attMap[$key] ?? null;

                $val = '8';
                if ($att) {
                    if ($att->status === 'off') {
                        $val = 'OFF';
                    } elseif ($att->status === 'absent') {
                        $val = 'A';
                    } else {
                        $val = (float) $att->work_hours + (float) $att->overtime_hours;
                    }
                }
                $sheet->setCellValue($colLetter . $dataRow, $val);
                $sheet->getStyle($colLetter . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            $sum = $summaries->get($emp->id);
            if (! $sum) {
                $sum = $this->securityService->calculateForEmployee($emp, $period);
            }

            $summaryValues = [
                $sum->total_days,
                $sum->total_work_hours,
                $sum->total_off_days,
                $sum->total_absent_days,
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

            foreach ($summaryValues as $idx => $sVal) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue($colLetter . $dataRow, $sVal);

                if (in_array($idx, [7, 9, 11, 12, 13], true)) {
                    $sheet->getStyle($colLetter . $dataRow)->getNumberFormat()->setFormatCode('#,##0');
                }
                $col++;
            }

            $totalOvertimeSum += (float) $sum->total_overtime_cost;
            $dataRow++;
        }

        // Footer Total Row
        $footerRow = $dataRow;
        $sheet->setCellValue('A' . $footerRow, 'TOTAL LEMBUR SECURITY');
        // Merge from A to column before total overtime cost
        $costColIndex = 3 + count($dates) + 12; // 0-based index offset for Total Biaya Lembur
        $costColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($costColIndex);
        $beforeCostLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($costColIndex - 1);

        $sheet->mergeCells('A' . $footerRow . ':' . $beforeCostLetter . $footerRow);
        $sheet->setCellValue($costColLetter . $footerRow, $totalOvertimeSum);
        $sheet->getStyle($costColLetter . $footerRow)->getNumberFormat()->setFormatCode('#,##0');

        $footerRange = 'A' . $footerRow . ':' . $maxColLetter . $footerRow;
        $sheet->getStyle($footerRange)->getFont()->setBold(true);
        $sheet->getStyle($footerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');

        // Borders
        $fullTableRange = 'A4:' . $maxColLetter . $footerRow;
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
