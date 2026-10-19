<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollPeriodRequest;
use App\Http\Requests\UpdatePayrollPeriodRequest;
use App\Models\Employee;
use App\Models\PayrollCalculationConfig;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\OvertimeCalculationService;
use App\Services\PayrollPeriodService;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPeriodController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PayrollPeriodService $periodService
    ) {}

    /**
     * Display a listing of payroll periods.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $query = PayrollPeriod::query()->withCount('payslips');

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        if ($request->filled('status')) {
            $query->filterStatus($request->string('status'));
        }

        $periods = $query->latest('start_date')->paginate(10)->withQueryString();

        $counts = [
            'total' => PayrollPeriod::count(),
            'draft' => PayrollPeriod::where('status', 'draft')->count(),
            'finalized' => PayrollPeriod::where('status', 'finalized')->count(),
        ];

        return Inertia::render('PayrollPeriods/Index', [
            'periods' => $periods,
            'counts' => $counts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show form for creating a new period.
     */
    public function create(): Response
    {
        $this->authorize('create', PayrollPeriod::class);

        // Suggest next period code based on current date e.g. "2026-10"
        $suggestedCode = now()->format('Y-m');
        $suggestedStart = now()->startOfMonth()->format('Y-m-d');
        $suggestedEnd = now()->endOfMonth()->format('Y-m-d');

        return Inertia::render('PayrollPeriods/Create', [
            'suggestedCode' => $suggestedCode,
            'suggestedStart' => $suggestedStart,
            'suggestedEnd' => $suggestedEnd,
        ]);
    }

    /**
     * Store a newly created period in storage.
     */
    public function store(StorePayrollPeriodRequest $request): RedirectResponse
    {
        $this->authorize('create', PayrollPeriod::class);

        $period = $this->periodService->createPeriod($request->validated());

        return redirect()
            ->route('payroll-periods.show', $period)
            ->with('success', "Periode gaji {$period->period_code} berhasil dibuat.");
    }

    /**
     * Display the specified payroll period and its slips.
     */
    public function show(Request $request, PayrollPeriod $payrollPeriod, OvertimeCalculationService $overtimeService): Response
    {
        $this->authorize('view', $payrollPeriod);

        $payrollPeriod->load(['creator:id,name,email', 'submitter:id,name', 'approver:id,name', 'finalizer:id,name']);

        $slipsQuery = $payrollPeriod->payslips()->with('employee:id,employee_number,full_name,department,phone');

        if ($request->filled('search')) {
            $slipsQuery->search($request->string('search')->trim());
        }

        if ($request->filled('status')) {
            $slipsQuery->filterStatus($request->string('status'));
        }

        $payslips = $slipsQuery->latest()->paginate(15)->withQueryString();

        // Financial totals for this period
        $totals = [
            'total_slips' => $payrollPeriod->payslips()->count(),
            'total_earnings' => (float) $payrollPeriod->payslips()->sum('total_earnings'),
            'total_deductions' => (float) $payrollPeriod->payslips()->sum('total_deductions'),
            'total_net' => (float) $payrollPeriod->payslips()->sum('net_salary'),
        ];

        // Overtime entries for this period
        $overtimeEntries = $payrollPeriod->overtimeEntries()
            ->with(['employee:id,employee_number,full_name,department,position,default_overtime_rate', 'creator:id,name', 'updater:id,name'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $activeEmployees = Employee::where('employment_status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'employee_number', 'full_name', 'department', 'position', 'default_overtime_rate']);

        // Overtime Recap per employee
        $entriesGrouped = $overtimeEntries->groupBy('employee_id');
        $overtimeRecap = [];
        $overtimeTotals = [
            'total_entries' => $overtimeEntries->count(),
            'total_working_day_hours' => 0.0,
            'total_holiday_hours' => 0.0,
            'total_break_deduction_hours' => 0.0,
            'total_net_hours' => 0.0,
            'total_upah_bruto' => '0.00',
            'total_potongan_lembur' => '0.00',
            'total_upah_bersih' => '0.00',
        ];

        foreach ($entriesGrouped as $empId => $entries) {
            $emp = $entries->first()->employee ?? Employee::find($empId);
            if (! $emp) {
                continue;
            }
            $recap = $overtimeService->calculateEmployeeOvertimeRecap($emp, $entries);
            $overtimeRecap[] = $recap;

            $overtimeTotals['total_working_day_hours'] += $recap['working_day']['gross_hours'];
            $overtimeTotals['total_holiday_hours'] += $recap['holiday']['gross_hours'];
            $overtimeTotals['total_break_deduction_hours'] += $recap['total_break_deduction_hours'];
            $overtimeTotals['total_net_hours'] += $recap['total_net_hours'];
            $overtimeTotals['total_upah_bruto'] = bcadd($overtimeTotals['total_upah_bruto'], $recap['total_upah_bruto'], 2);
            $overtimeTotals['total_potongan_lembur'] = bcadd($overtimeTotals['total_potongan_lembur'], $recap['total_potongan_lembur'], 2);
            $overtimeTotals['total_upah_bersih'] = bcadd($overtimeTotals['total_upah_bersih'], $recap['total_upah_lembur'], 2);
        }

        // Active Overtime Configuration
        $workingConfig = PayrollCalculationConfig::with('currentVersionRecord')->active()->forDayType('working_day')->first();
        $holidayConfig = PayrollCalculationConfig::with('currentVersionRecord')->active()->forDayType('holiday')->first();
        $overtimeConfig = [
            'working_day_threshold' => (float) ($workingConfig?->currentVersionRecord?->formula['break_threshold'] ?? 3.0),
            'working_day_duration' => (float) ($workingConfig?->currentVersionRecord?->formula['break_duration_per_day'] ?? 0.5),
            'holiday_threshold' => (float) ($holidayConfig?->currentVersionRecord?->formula['break_threshold'] ?? 4.0),
            'holiday_duration' => (float) ($holidayConfig?->currentVersionRecord?->formula['break_duration_per_day'] ?? 1.0),
        ];

        $user = $request->user();

        return Inertia::render('PayrollPeriods/Show', [
            'period' => $payrollPeriod,
            'payslips' => $payslips,
            'totals' => $totals,
            'overtimeEntries' => $overtimeEntries,
            'overtimeRecap' => $overtimeRecap,
            'overtimeTotals' => $overtimeTotals,
            'overtimeConfig' => $overtimeConfig,
            'activeEmployees' => $activeEmployees,
            'filters' => $request->only(['search', 'status']),
            'can' => [
                'update' => $user ? $user->can('update', $payrollPeriod) : false,
                'delete' => $user ? $user->can('delete', $payrollPeriod) : false,
                'submit' => $user ? $user->can('submit', $payrollPeriod) : false,
                'finalize' => $user ? $user->can('finalize', $payrollPeriod) : false,
                'create_payslip' => $user ? $user->can('create', Payslip::class) : false,
                'generate_payroll' => $user ? ($user->can('update', $payrollPeriod) && $payrollPeriod->canBeModified()) : false,
                'manage_overtime' => $user ? ($user->can('update', $payrollPeriod) && $payrollPeriod->canBeModified()) : false,
            ],
        ]);
    }

    /**
     * Show form for editing period dates.
     */
    public function edit(PayrollPeriod $payrollPeriod): Response
    {
        $this->authorize('update', $payrollPeriod);

        return Inertia::render('PayrollPeriods/Edit', [
            'period' => $payrollPeriod,
        ]);
    }

    /**
     * Update the specified period in storage.
     */
    public function update(UpdatePayrollPeriodRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorize('update', $payrollPeriod);

        try {
            $this->periodService->updatePeriod($payrollPeriod, $request->validated());

            return redirect()
                ->route('payroll-periods.show', $payrollPeriod)
                ->with('success', "Periode gaji {$payrollPeriod->period_code} berhasil diperbarui.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified period from storage.
     */
    public function destroy(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorize('delete', $payrollPeriod);

        try {
            $code = $payrollPeriod->period_code;
            $this->periodService->deletePeriod($payrollPeriod);

            return redirect()
                ->route('payroll-periods.index')
                ->with('success', "Periode gaji {$code} berhasil dihapus.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Finalize an approved payroll period and all its payslips.
     */
    public function finalize(Request $request, PayrollPeriod $payrollPeriod, PayslipFinalizationService $finalizationService): RedirectResponse
    {
        $this->authorize('finalize', $payrollPeriod);

        try {
            $finalizationService->finalizePeriod($payrollPeriod, $request->user());

            return redirect()
                ->route('payroll-periods.show', $payrollPeriod)
                ->with('success', "Periode gaji {$payrollPeriod->period_code} dan seluruh slip gaji terkait berhasil difinalisasi.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Auto generate payslips for all active employees for this payroll period.
     */
    public function generatePayroll(Request $request, PayrollPeriod $payrollPeriod, PayslipService $payslipService): RedirectResponse
    {
        $this->authorize('update', $payrollPeriod);

        try {
            $result = $payslipService->generatePayrollForPeriod($payrollPeriod, $request->user()?->id);

            return redirect()
                ->route('payroll-periods.show', $payrollPeriod)
                ->with('success', "Auto generate payroll berhasil diproses: {$result['created']} slip dibuat, {$result['updated']} slip diperbarui, {$result['skipped']} dilewati.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
