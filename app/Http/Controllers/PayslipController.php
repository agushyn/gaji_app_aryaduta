<?php

namespace App\Http\Controllers;

use App\Enums\PayslipStatus;
use App\Http\Requests\StorePayslipRequest;
use App\Http\Requests\UpdatePayslipRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipDelivery;
use App\Services\AuditLogService;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipPdfService;
use App\Services\PayslipService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayslipController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PayslipService $payslipService
    ) {}

    /**
     * Display a listing of payslips across all or specific period.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payslip::class);

        $query = Payslip::query()
            ->with(['employee:id,employee_number,full_name,department,phone', 'payrollPeriod:id,period_code,status']);

        if ($request->filled('period_id')) {
            $query->where('payroll_period_id', $request->input('period_id'));
        }

        if ($request->filled('status')) {
            $query->filterStatus($request->string('status'));
        }

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        $payslips = $query->latest()->paginate(15)->withQueryString();

        $periods = PayrollPeriod::latest('start_date')
            ->select('id', 'period_code', 'status')
            ->get();

        $counts = [
            'total' => Payslip::count(),
            'draft' => Payslip::where('status', 'draft')->count(),
            'finalized' => Payslip::where('status', 'finalized')->count(),
        ];

        return Inertia::render('Payslips/Index', [
            'payslips' => $payslips,
            'periods' => $periods,
            'counts' => $counts,
            'filters' => $request->only(['period_id', 'status', 'search']),
        ]);
    }

    /**
     * Show form for creating a new manual payslip.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Payslip::class);

        $selectedPeriodId = $request->input('period_id');
        $selectedPeriod = null;

        if ($selectedPeriodId) {
            $selectedPeriod = PayrollPeriod::find($selectedPeriodId);
        }

        if (! $selectedPeriod) {
            $selectedPeriod = PayrollPeriod::whereIn('status', ['draft', 'changes_requested'])
                ->latest('start_date')
                ->first();
        }

        // Available periods
        $periods = PayrollPeriod::whereIn('status', ['draft', 'changes_requested'])
            ->latest('start_date')
            ->get();

        // Active employees who don't have slip in this selected period yet
        $existingEmployeeIds = [];
        if ($selectedPeriod) {
            $existingEmployeeIds = Payslip::where('payroll_period_id', $selectedPeriod->id)->pluck('employee_id')->toArray();
        }

        $employees = Employee::where('employment_status', 'active')
            ->orderBy('full_name')
            ->get([
                'id',
                'employee_number',
                'full_name',
                'department',
                'position',
                'gaji_pokok',
                'tunjangan_kehadiran',
                'tunjangan_keluarga',
                'tunjangan_jabatan',
                'uang_makan_per_hari',
                'uang_transportasi_per_hari',
                'bpjs_ketenagakerjaan_enabled',
                'bpjs_ketenagakerjaan_amount',
            ]);

        $payComponents = PayComponent::where('is_active', true)
            ->ordered()
            ->get();

        return Inertia::render('Payslips/Create', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'employees' => $employees,
            'existingEmployeeIds' => $existingEmployeeIds,
            'payComponents' => $payComponents,
        ]);
    }

    /**
     * Store a newly created payslip in storage.
     */
    public function store(StorePayslipRequest $request): RedirectResponse
    {
        $this->authorize('create', Payslip::class);

        $period = PayrollPeriod::findOrFail($request->input('payroll_period_id'));

        try {
            $payslip = $this->payslipService->createPayslip($period, $request->validated());

            if (OvertimeEntry::where('payroll_period_id', $period->id)->where('employee_id', $payslip->employee_id)->exists()) {
                $this->payslipService->syncEmployeeOvertime($period, $payslip->employee_id);
            }

            return redirect()
                ->route('payroll-periods.show', $period)
                ->with('success', "Slip gaji {$payslip->slip_number} untuk {$payslip->employee_name_snapshot} berhasil dibuat.");
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified payslip.
     */
    public function show(Request $request, Payslip $payslip): Response
    {
        $this->authorize('view', $payslip);

        $payslip->load([
            'items',
            'employee',
            'payrollPeriod',
            'creator:id,name,email',
            'approver:id,name',
            'finalizer:id,name',
            'voidedBy:id,name',
            'deliveries.attempts',
        ]);

        $auditLogs = AuditLog::with('user:id,name,email')
            ->where('subject_type', Payslip::class)
            ->where('subject_id', (string) $payslip->id)
            ->latest('created_at')
            ->take(15)
            ->get();

        $user = $request->user();

        return Inertia::render('Payslips/Show', [
            'payslip' => $payslip,
            'auditLogs' => $auditLogs,
            'can' => [
                'update' => $user ? $user->can('update', $payslip) : false,
                'delete' => $user ? $user->can('delete', $payslip) : false,
                'submit' => $user ? $user->can('submit', $payslip) : false,
                'finalize' => $user ? $user->can('finalize', $payslip) : false,
                'void' => $user ? $user->can('void', $payslip) : false,
                'preview' => $user ? $user->can('preview', $payslip) : false,
                'download' => $user ? $user->can('download', $payslip) : false,
                'sendEmail' => $user ? $user->can('send', PayslipDelivery::class) : false,
                'sendWhatsApp' => $user ? $user->can('send', PayslipDelivery::class) : false,
                'retryDelivery' => $user ? $user->hasPermission('deliveries.retry') : false,
            ],
        ]);
    }

    /**
     * Preview payslip PDF inline in browser.
     */
    public function preview(Payslip $payslip, PayslipPdfService $pdfService, AuditLogService $auditLogService): mixed
    {
        $this->authorize('preview', $payslip);

        $auditLogService->log(
            action: 'payslip.pdf_previewed',
            subject: $payslip,
            changes: [
                'slip_number' => $payslip->slip_number,
                'status' => $payslip->status,
            ]
        );

        return $pdfService->previewPdf($payslip);
    }

    /**
     * Download payslip PDF attachment.
     */
    public function download(Payslip $payslip, PayslipPdfService $pdfService, AuditLogService $auditLogService): mixed
    {
        $this->authorize('download', $payslip);

        $auditLogService->log(
            action: 'payslip.pdf_downloaded',
            subject: $payslip,
            changes: [
                'slip_number' => $payslip->slip_number,
                'status' => $payslip->status,
            ]
        );

        return $pdfService->downloadPdf($payslip);
    }

    /**
     * Download payslip PDF via temporary signed URL (used when WhatsApp gateway requires private expiring URL).
     */
    public function downloadSigned(Request $request, Payslip $payslip, PayslipPdfService $pdfService, AuditLogService $auditLogService): mixed
    {
        abort_if(! $request->hasValidSignature(), 401, 'Tautan bertanda tangan telah kedaluwarsa atau tidak valid.');
        abort_if($payslip->status !== PayslipStatus::FINALIZED->value, 403, 'Hanya slip gaji final yang dapat diakses.');

        $auditLogService->log(
            action: 'payslip.pdf_signed_url_accessed',
            subject: $payslip,
            changes: [
                'slip_number' => $payslip->slip_number,
                'ip' => $request->ip(),
            ]
        );

        return $pdfService->downloadPdf($payslip);
    }

    /**
     * Finalize an approved payslip.
     */
    public function finalize(Request $request, Payslip $payslip, PayslipFinalizationService $finalizationService): RedirectResponse
    {
        $this->authorize('finalize', $payslip);

        try {
            $finalizationService->finalizePayslip($payslip, $request->user());

            return redirect()
                ->route('payslips.show', $payslip)
                ->with('success', "Slip gaji {$payslip->slip_number} berhasil difinalisasi dan dokumen PDF privat telah digenerate.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Void a finalized payslip with recorded reason.
     */
    public function void(Request $request, Payslip $payslip): RedirectResponse
    {
        $this->authorize('void', $payslip);

        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pembatalan (void) wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        try {
            $this->payslipService->voidPayslip($payslip, $request->user(), $request->input('reason'));

            return redirect()
                ->route('payslips.show', $payslip)
                ->with('success', "Slip gaji {$payslip->slip_number} telah berhasil dibatalkan (void).");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show form for editing the specified payslip.
     */
    public function edit(Payslip $payslip): Response
    {
        $this->authorize('update', $payslip);

        $payslip->load(['items', 'employee', 'payrollPeriod']);

        $payComponents = PayComponent::where('is_active', true)
            ->ordered()
            ->get();

        return Inertia::render('Payslips/Edit', [
            'payslip' => $payslip,
            'payComponents' => $payComponents,
        ]);
    }

    /**
     * Update the specified payslip in storage.
     */
    public function update(UpdatePayslipRequest $request, Payslip $payslip): RedirectResponse
    {
        $this->authorize('update', $payslip);

        try {
            $this->payslipService->updatePayslip($payslip, $request->validated());

            return redirect()
                ->route('payslips.show', $payslip)
                ->with('success', "Slip gaji {$payslip->slip_number} berhasil diperbarui.");
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified payslip from storage.
     */
    public function destroy(Payslip $payslip): RedirectResponse
    {
        $this->authorize('delete', $payslip);

        $periodId = $payslip->payroll_period_id;
        $slipNumber = $payslip->slip_number;

        try {
            $this->payslipService->deletePayslip($payslip);

            return redirect()
                ->route('payroll-periods.show', $periodId)
                ->with('success', "Slip gaji {$slipNumber} berhasil dihapus.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
