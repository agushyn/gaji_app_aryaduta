<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Display comprehensive delivery reports with multi-criteria filtering.
     */
    public function index(Request $request): Response
    {
        abort_if(! $request->user()->hasPermission('reports.view'), 403, 'Akses ditolak.');

        $filters = $request->only(['period_id', 'department', 'channel', 'status', 'search']);

        $reportData = $this->reportService->getDeliveryReport($filters, $request->user());

        $periods = PayrollPeriod::latest('start_date')
            ->select('id', 'period_code', 'status')
            ->get();

        $departments = Employee::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return Inertia::render('Reports/Deliveries', [
            'summary' => $reportData['summary'],
            'departmentsBreakdown' => $reportData['departments'],
            'deliveries' => $reportData['deliveries'],
            'canViewFinancial' => $reportData['can_view_financial'],
            'periods' => $periods,
            'departmentOptions' => $departments,
            'filters' => $filters,
            'can' => [
                'export' => $request->user()->hasPermission('reports.export'),
                'viewFinancial' => $request->user()->hasPermission('payslips.financial-view'),
                'send' => $request->user()->hasPermission('deliveries.send'),
            ],
        ]);
    }

    /**
     * Export filtered delivery records to CSV with role-based nominal protection.
     */
    public function export(Request $request): StreamedResponse
    {
        abort_if(! $request->user()->hasPermission('reports.export'), 403, 'Akses ditolak.');

        $filters = $request->only(['period_id', 'department', 'channel', 'status', 'search']);

        return $this->reportService->exportCsv($filters, $request->user());
    }
}
