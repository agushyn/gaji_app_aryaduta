<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\PayrollPeriod;
use App\Services\AuditLogService;
use App\Services\Security\SecurityPayrollExportService;
use App\Services\Security\SecurityPayrollReportService;
use App\Services\Security\SecurityReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityPayrollReportController extends Controller
{
    public function __construct(
        protected SecurityPayrollReportService $reportService,
        protected SecurityPayrollExportService $exportService,
        protected SecurityReconciliationService $reconciliationService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display the Security Payroll Reports & Reconciliation dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.view') && ! $user->hasPermission('payslips.view') && ! $user->hasPermission('reports.view'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat laporan Payroll Security.');
        }

        $periods = PayrollPeriod::orderByDesc('start_date')->get();

        $selectedPeriodId = $request->input('period_id');
        $currentPeriod = $selectedPeriodId
            ? PayrollPeriod::find($selectedPeriodId)
            : $periods->first();

        $activeTab = $request->input('tab', 'dashboard');
        $filters = [
            'search' => $request->input('search'),
            'office_id' => $request->input('office_id'),
            'department' => $request->input('department'),
            'overtime_category' => $request->input('overtime_category', 'all'),
            'status' => $request->input('status', 'all'),
            'period_id' => $selectedPeriodId ?: ($currentPeriod?->id ?? null),
        ];

        $offices = Office::orderBy('name')->get(['id', 'name']);

        if (! $currentPeriod) {
            return Inertia::render('SecurityPayroll/Reports', [
                'periods' => $periods,
                'currentPeriod' => null,
                'activeTab' => $activeTab,
                'filters' => $filters,
                'offices' => $offices,
                'metrics' => null,
                'reportData' => null,
            ]);
        }

        // Metrics are always loaded to power the top KPI summary cards
        $metrics = $this->reportService->getDashboardMetrics($currentPeriod, $filters);

        // Load data specific to the current tab
        $reportData = match ($activeTab) {
            'dashboard' => [
                'period_summaries' => $this->reportService->getPeriodSummaryReport($filters),
            ],
            'detail' => [
                'employees' => $this->reportService->getEmployeeDetailReport($currentPeriod, $filters),
            ],
            'shortage' => [
                'shortages' => $this->reportService->getShortageReport($currentPeriod, $filters),
            ],
            'overtime' => [
                'overtimes' => $this->reportService->getOvertimeReport($currentPeriod, $filters),
            ],
            'reconciliation' => [
                'reconciliation' => $this->reconciliationService->reconcilePeriod($currentPeriod, $filters),
            ],
            'audit' => [
                'events' => $this->reportService->getAuditHistoryReport($currentPeriod, $filters),
            ],
            default => [
                'period_summaries' => $this->reportService->getPeriodSummaryReport($filters),
            ],
        };

        return Inertia::render('SecurityPayroll/Reports', [
            'periods' => $periods,
            'currentPeriod' => $currentPeriod,
            'activeTab' => $activeTab,
            'filters' => $filters,
            'offices' => $offices,
            'metrics' => $metrics,
            'reportData' => $reportData,
        ]);
    }

    /**
     * Export Security Payroll report as XLSX or CSV.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('reports.export') && ! $user->hasPermission('payroll.security.view'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengekspor laporan Payroll Security.');
        }

        $periodId = $request->input('period_id');
        $period = $periodId
            ? PayrollPeriod::findOrFail($periodId)
            : PayrollPeriod::orderByDesc('start_date')->firstOrFail();

        $format = $request->input('format', 'xlsx');
        $filters = [
            'search' => $request->input('search'),
            'office_id' => $request->input('office_id'),
            'department' => $request->input('department'),
            'overtime_category' => $request->input('overtime_category', 'all'),
            'status' => $request->input('status', 'all'),
            'period_id' => $period->id,
        ];

        return $this->exportService->export($type, $period, $format, $filters, $user);
    }

    /**
     * Trigger explicit reconciliation for a period (AJAX or Redirect).
     */
    public function reconcile(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('payroll.security.view') && ! $user->hasPermission('payslips.view'))) {
            abort(403, 'Anda tidak memiliki hak akses untuk menjalankan rekonsiliasi.');
        }

        $validated = $request->validate([
            'period_id' => 'required|exists:payroll_periods,id',
        ]);

        $period = PayrollPeriod::findOrFail($validated['period_id']);
        $filters = [
            'search' => $request->input('search'),
            'office_id' => $request->input('office_id'),
            'department' => $request->input('department'),
        ];

        $results = $this->reconciliationService->reconcilePeriod($period, $filters);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Rekonsiliasi periode berhasil dieksekusi.',
                'data' => $results,
            ]);
        }

        return redirect()->route('payroll-security.reports.index', [
            'period_id' => $period->id,
            'tab' => 'reconciliation',
        ])->with('success', 'Rekonsiliasi payroll security berhasil dijalankan.');
    }
}

