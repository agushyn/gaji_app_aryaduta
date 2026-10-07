<?php

namespace App\Http\Controllers;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Http\Requests\ApprovePayrollRequest;
use App\Http\Requests\RequestChangesPayrollRequest;
use App\Http\Requests\SubmitPayrollRequest;
use App\Models\PayrollApprovalEvent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\PayrollApprovalService;
use App\Services\PayrollSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

use App\Services\PayrollExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalController extends Controller
{
    /**
     * Display a listing of payroll periods and approvals queue.
     */
    public function index(Request $request): Response
    {
        abort_if(! $request->user()->hasPermission('approvals.view'), 403, 'Akses ditolak.');

        $statusTab = $request->input('status', 'in_review');

        $metrics = [
            'in_review' => PayrollPeriod::where('status', PayrollPeriodStatus::IN_REVIEW->value)->count(),
            'changes_requested' => PayrollPeriod::where('status', PayrollPeriodStatus::CHANGES_REQUESTED->value)->count(),
            'approved' => PayrollPeriod::where('status', PayrollPeriodStatus::APPROVED->value)->count(),
            'all' => PayrollPeriod::count(),
        ];

        $query = PayrollPeriod::with(['creator', 'submitter', 'approver'])
            ->withCount('payslips')
            ->latest('id');

        if ($statusTab !== 'all') {
            $query->where('status', $statusTab);
        }

        $periods = $query->paginate(15)->through(function (PayrollPeriod $p) use ($request) {
            $totalNet = $p->payslips()->sum('net_salary');

            return [
                'id' => $p->id,
                'period_code' => $p->period_code,
                'start_date' => $p->start_date?->format('Y-m-d'),
                'end_date' => $p->end_date?->format('Y-m-d'),
                'status' => $p->status,
                'status_label' => $p->status_label,
                'badge_class' => PayrollPeriodStatus::tryFrom($p->status)?->badgeClass(),
                'payslips_count' => $p->payslips_count,
                'total_net_salary' => (float) $totalNet,
                'formatted_net_salary' => 'Rp '.number_format((float) $totalNet, 0, ',', '.'),
                'submitter_name' => $p->submitter?->name,
                'submitted_at' => $p->submitted_at?->format('d/m/Y H:i'),
                'approver_name' => $p->approver?->name,
                'approved_at' => $p->approved_at?->format('d/m/Y H:i'),
                'can_approve' => Gate::forUser($request->user())->allows('approve', $p),
                'can_request_changes' => Gate::forUser($request->user())->allows('requestChanges', $p),
            ];
        })->withQueryString();

        return Inertia::render('Approvals/Index', [
            'periods' => $periods,
            'metrics' => $metrics,
            'currentTab' => $statusTab,
            'canDecide' => $request->user()->hasPermission('approvals.decide'),
        ]);
    }

    /**
     * Display the approval detail review page for a payroll period.
     */
    public function showPeriod(Request $request, PayrollPeriod $payrollPeriod): Response
    {
        abort_if(! $request->user()->hasPermission('approvals.view'), 403, 'Akses ditolak.');

        $payrollPeriod->load([
            'creator',
            'submitter',
            'approver',
            'finalizer',
            'approvalEvents.actor',
            'payslips.employee',
        ]);

        $payslips = $payrollPeriod->payslips->map(function (Payslip $s) {
            return [
                'id' => $s->id,
                'slip_number' => $s->slip_number,
                'employee_name' => $s->employee_name_snapshot,
                'employee_number' => $s->employee_number_snapshot,
                'department' => $s->department_snapshot,
                'position' => $s->position_snapshot,
                'basic_salary' => (float) $s->basic_salary,
                'total_earnings' => (float) $s->total_earnings,
                'total_deductions' => (float) $s->total_deductions,
                'net_salary' => (float) $s->net_salary,
                'formatted_net_salary' => $s->formatted_net_salary,
                'status' => $s->status,
                'status_label' => $s->status_label,
                'badge_class' => PayslipStatus::tryFrom($s->status)?->badgeClass(),
            ];
        });

        $events = $payrollPeriod->approvalEvents->map(function (PayrollApprovalEvent $e) {
            return [
                'id' => $e->id,
                'decision' => $e->decision,
                'decision_label' => $e->decision_label,
                'badge_class' => $e->badge_class,
                'comment' => $e->comment,
                'actor_name' => $e->actor?->name ?? 'Sistem',
                'created_at' => $e->created_at?->format('d/m/Y H:i:s'),
                'payslip_id' => $e->payslip_id,
            ];
        });

        $totalBasic = $payrollPeriod->payslips->sum(fn ($s) => (float) $s->basic_salary);
        $totalEarnings = $payrollPeriod->payslips->sum(fn ($s) => (float) $s->total_earnings);
        $totalDeductions = $payrollPeriod->payslips->sum(fn ($s) => (float) $s->total_deductions);
        $totalNet = $payrollPeriod->payslips->sum(fn ($s) => (float) $s->net_salary);

        $currentUser = $request->user();
        $isCreatorOrSubmitter = $currentUser->id === (int) $payrollPeriod->created_by ||
            $currentUser->id === (int) $payrollPeriod->submitted_by;
        $segregationEnforced = config('payroll.enforce_segregation_of_duties', true);

        return Inertia::render('Approvals/PeriodShow', [
            'period' => [
                'id' => $payrollPeriod->id,
                'period_code' => $payrollPeriod->period_code,
                'start_date' => $payrollPeriod->start_date?->format('Y-m-d'),
                'end_date' => $payrollPeriod->end_date?->format('Y-m-d'),
                'status' => $payrollPeriod->status,
                'status_label' => $payrollPeriod->status_label,
                'badge_class' => PayrollPeriodStatus::tryFrom($payrollPeriod->status)?->badgeClass(),
                'approval_comment' => $payrollPeriod->approval_comment,
                'creator_name' => $payrollPeriod->creator?->name,
                'submitter_name' => $payrollPeriod->submitter?->name,
                'submitted_at' => $payrollPeriod->submitted_at?->format('d/m/Y H:i'),
                'approver_name' => $payrollPeriod->approver?->name,
                'approved_at' => $payrollPeriod->approved_at?->format('d/m/Y H:i'),
                'finalizer_name' => $payrollPeriod->finalizer?->name,
                'finalized_at' => $payrollPeriod->finalized_at?->format('d/m/Y H:i'),
                'payslips_count' => $payrollPeriod->payslips->count(),
                'total_basic' => $totalBasic,
                'total_earnings' => $totalEarnings,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
                'formatted_net' => 'Rp '.number_format($totalNet, 0, ',', '.'),
            ],
            'payslips' => $payslips,
            'events' => $events,
            'canApprove' => Gate::forUser($currentUser)->allows('approve', $payrollPeriod),
            'canRequestChanges' => Gate::forUser($currentUser)->allows('requestChanges', $payrollPeriod),
            'canSubmit' => Gate::forUser($currentUser)->allows('submit', $payrollPeriod),
            'canFinalize' => Gate::forUser($currentUser)->allows('finalize', $payrollPeriod),
            'isCreatorOrSubmitter' => $isCreatorOrSubmitter,
            'segregationEnforced' => $segregationEnforced,
        ]);
    }

    /**
     * Submit a period for review.
     */
    public function submitPeriod(SubmitPayrollRequest $request, PayrollPeriod $payrollPeriod, PayrollSubmissionService $service): RedirectResponse
    {
        Gate::authorize('submit', $payrollPeriod);

        $service->submitPeriod($payrollPeriod, $request->user(), $request->input('notes'));

        return redirect()->back()->with('success', "Periode {$payrollPeriod->period_code} berhasil diajukan untuk proses review persetujuan.");
    }

    /**
     * Approve a period and its payslips.
     */
    public function approvePeriod(ApprovePayrollRequest $request, PayrollPeriod $payrollPeriod, PayrollApprovalService $service): RedirectResponse
    {
        Gate::authorize('approve', $payrollPeriod);

        $service->approvePeriod($payrollPeriod, $request->user(), $request->input('comment'));

        return redirect()->back()->with('success', "Periode {$payrollPeriod->period_code} dan seluruh slip di dalamnya berhasil disetujui.");
    }

    /**
     * Request changes for a period.
     */
    public function requestPeriodChanges(RequestChangesPayrollRequest $request, PayrollPeriod $payrollPeriod, PayrollApprovalService $service): RedirectResponse
    {
        Gate::authorize('requestChanges', $payrollPeriod);

        $service->requestPeriodChanges($payrollPeriod, $request->user(), $request->input('comment'));

        return redirect()->back()->with('success', "Permintaan perbaikan untuk periode {$payrollPeriod->period_code} berhasil dikirimkan ke personalia.");
    }

    /**
     * Submit an individual payslip for review.
     */
    public function submitPayslip(SubmitPayrollRequest $request, Payslip $payslip, PayrollSubmissionService $service): RedirectResponse
    {
        Gate::authorize('submit', $payslip);

        $service->submitPayslip($payslip, $request->user(), $request->input('notes'));

        return redirect()->back()->with('success', "Slip gaji {$payslip->slip_number} berhasil diajukan untuk review.");
    }

    /**
     * Approve an individual payslip.
     */
    public function approvePayslip(ApprovePayrollRequest $request, Payslip $payslip, PayrollApprovalService $service): RedirectResponse
    {
        Gate::authorize('approve', $payslip);

        $service->approvePayslip($payslip, $request->user(), $request->input('comment'));

        return redirect()->back()->with('success', "Slip gaji {$payslip->slip_number} berhasil disetujui.");
    }

    /**
     * Request changes for an individual payslip.
     */
    public function requestPayslipChanges(RequestChangesPayrollRequest $request, Payslip $payslip, PayrollApprovalService $service): RedirectResponse
    {
        Gate::authorize('requestChanges', $payslip);

        $service->requestPayslipChanges($payslip, $request->user(), $request->input('comment'));

        return redirect()->back()->with('success', "Permintaan perbaikan untuk slip gaji {$payslip->slip_number} telah dicatat.");
    }

    /**
     * Export payroll data for a period as a dual-sheet XLSX document.
     */
    public function exportExcel(Request $request, PayrollPeriod $payrollPeriod, PayrollExportService $exportService): StreamedResponse
    {
        abort_if(! $request->user()->hasPermission('approvals.view') && ! $request->user()->hasPermission('reports.view'), 403, 'Akses ditolak.');

        return $exportService->exportPayrollXlsx($payrollPeriod);
    }
}

