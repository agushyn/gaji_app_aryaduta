<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollCalculationConfigRequest;
use App\Http\Requests\UpdatePayrollCalculationConfigRequest;
use App\Models\PayComponent;
use App\Models\PayrollCalculationConfig;
use App\Services\FormulaEvaluatorService;
use App\Services\OvertimeCalculationService;
use App\Services\PayrollCalculationConfigService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PayrollCalculationConfigController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PayrollCalculationConfigService $configService,
        protected FormulaEvaluatorService $formulaEvaluator,
        protected OvertimeCalculationService $overtimeService
    ) {}

    /**
     * Display a listing of calculation configs.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollCalculationConfig::class);

        $query = PayrollCalculationConfig::query()
            ->with(['payComponent', 'currentVersionRecord']);

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('day_type')) {
            $query->where('day_type', $request->string('day_type'));
        }

        if ($request->filled('status')) {
            $isActive = $request->string('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $configs = $query->latest('updated_at')->paginate(15)->withQueryString();

        // Transform configs with human-readable formula preview
        $configs->getCollection()->transform(function ($config) {
            $currentFormula = $config->currentVersionRecord?->formula;
            if (empty($currentFormula)) {
                $currentFormula = $config->day_type === 'holiday'
                    ? $this->overtimeService->getDefaultHolidayFormula()
                    : $this->overtimeService->getDefaultWorkingDayFormula();
            }
            $previewDetails = $this->overtimeService->getFormulaPreview($currentFormula, $config->day_type);

            return [
                'id' => $config->id,
                'name' => $config->name,
                'code' => $config->code,
                'day_type' => $config->day_type,
                'day_type_label' => match ($config->day_type) {
                    'working_day' => 'Hari Kerja',
                    'holiday' => 'Hari Libur',
                    'national_holiday' => 'Hari Libur Nasional',
                    'joint_leave' => 'Cuti Bersama',
                    'weekend' => 'Weekend',
                    default => 'Semua Hari',
                },
                'is_active' => (bool) $config->is_active,
                'current_version' => $config->current_version,
                'updated_at' => $config->updated_at?->translatedFormat('d F Y H:i') ?? '-',
                'pay_component' => $config->payComponent ? [
                    'id' => $config->payComponent->id,
                    'name' => $config->payComponent->name,
                    'code' => $config->payComponent->code,
                ] : null,
                'formula_preview' => $previewDetails['text'],
                'preview_details' => $previewDetails,
                'is_used_in_finalized_payslips' => $config->isUsedInFinalizedPayslips(),
            ];
        });

        $counts = [
            'total' => PayrollCalculationConfig::count(),
            'active' => PayrollCalculationConfig::where('is_active', true)->count(),
            'working_day' => PayrollCalculationConfig::where('day_type', 'working_day')->count(),
            'holiday' => PayrollCalculationConfig::where('day_type', 'holiday')->count(),
        ];

        return Inertia::render('PayrollConfigurations/Index', [
            'configs' => $configs,
            'counts' => $counts,
            'filters' => $request->only(['search', 'day_type', 'status']),
        ]);
    }

    /**
     * Show form to create new calculation configuration.
     */
    public function create(): Response
    {
        $this->authorize('create', PayrollCalculationConfig::class);

        $payComponents = PayComponent::where('is_active', true)
            ->ordered()
            ->get(['id', 'code', 'name', 'type']);

        $defaultWorkingDayFormula = $this->overtimeService->getDefaultWorkingDayFormula();
        $defaultHolidayFormula = $this->overtimeService->getDefaultHolidayFormula();

        return Inertia::render('PayrollConfigurations/Create', [
            'payComponents' => $payComponents,
            'defaultWorkingDayFormula' => $defaultWorkingDayFormula,
            'defaultHolidayFormula' => $defaultHolidayFormula,
            'variableLabels' => FormulaEvaluatorService::VARIABLE_LABELS,
        ]);
    }

    /**
     * Store newly created configuration.
     */
    public function store(StorePayrollCalculationConfigRequest $request): RedirectResponse
    {
        $this->authorize('create', PayrollCalculationConfig::class);

        $config = $this->configService->createConfig($request->validated());

        return redirect()
            ->route('payroll-configurations.index')
            ->with('success', "Konfigurasi perhitungan '{$config->name}' berhasil dibuat dengan Versi 1.");
    }

    /**
     * Show form to edit configuration.
     */
    public function edit(PayrollCalculationConfig $payrollConfiguration): Response
    {
        $this->authorize('update', $payrollConfiguration);

        $payrollConfiguration->load(['payComponent', 'currentVersionRecord', 'versions' => function ($q) {
            $q->latest('version');
        }]);

        $payComponents = PayComponent::where('is_active', true)
            ->ordered()
            ->get(['id', 'code', 'name', 'type']);

        $currentFormula = $payrollConfiguration->currentVersionRecord?->formula;
        if (empty($currentFormula)) {
            $currentFormula = $payrollConfiguration->day_type === 'holiday'
                ? $this->overtimeService->getDefaultHolidayFormula()
                : $this->overtimeService->getDefaultWorkingDayFormula();
        }

        return Inertia::render('PayrollConfigurations/Edit', [
            'config' => [
                'id' => $payrollConfiguration->id,
                'name' => $payrollConfiguration->name,
                'code' => $payrollConfiguration->code,
                'component_id' => $payrollConfiguration->component_id,
                'day_type' => $payrollConfiguration->day_type,
                'is_active' => (bool) $payrollConfiguration->is_active,
                'current_version' => $payrollConfiguration->current_version,
                'formula' => $currentFormula,
                'description' => $payrollConfiguration->currentVersionRecord?->description,
                'is_used_in_finalized_payslips' => $payrollConfiguration->isUsedInFinalizedPayslips(),
            ],
            'versions' => $payrollConfiguration->versions->map(function ($v) use ($payrollConfiguration) {
                $previewDetails = $this->overtimeService->getFormulaPreview($v->formula, $payrollConfiguration->day_type);

                return [
                    'id' => $v->id,
                    'version' => $v->version,
                    'formula' => $v->formula,
                    'preview' => $previewDetails['text'],
                    'preview_details' => $previewDetails,
                    'description' => $v->description,
                    'is_active' => (bool) $v->is_active,
                    'created_at' => $v->created_at?->translatedFormat('d M Y H:i'),
                ];
            }),
            'payComponents' => $payComponents,
            'variableLabels' => FormulaEvaluatorService::VARIABLE_LABELS,
        ]);
    }

    /**
     * Update configuration.
     */
    public function update(
        UpdatePayrollCalculationConfigRequest $request,
        PayrollCalculationConfig $payrollConfiguration
    ): RedirectResponse {
        $this->authorize('update', $payrollConfiguration);

        $config = $this->configService->updateConfig($payrollConfiguration, $request->validated());

        return redirect()
            ->route('payroll-configurations.index')
            ->with('success', "Konfigurasi '{$config->name}' berhasil diperbarui (Versi aktif: {$config->current_version}).");
    }

    /**
     * Duplicate configuration.
     */
    public function duplicate(PayrollCalculationConfig $payrollConfiguration): RedirectResponse
    {
        $this->authorize('create', PayrollCalculationConfig::class);

        $newConfig = $this->configService->duplicateConfig($payrollConfiguration);

        return redirect()
            ->route('payroll-configurations.index')
            ->with('success', "Konfigurasi '{$newConfig->name}' berhasil diduplikasi.");
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(PayrollCalculationConfig $payrollConfiguration): RedirectResponse
    {
        $this->authorize('update', $payrollConfiguration);

        $updated = $this->configService->toggleActive($payrollConfiguration);
        $statusText = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->back()
            ->with('success', "Konfigurasi '{$updated->name}' berhasil {$statusText}.");
    }

    /**
     * Remove configuration.
     */
    public function destroy(PayrollCalculationConfig $payrollConfiguration): RedirectResponse
    {
        $this->authorize('delete', $payrollConfiguration);

        try {
            $name = $payrollConfiguration->name;
            $this->configService->deleteConfig($payrollConfiguration);

            return redirect()
                ->route('payroll-configurations.index')
                ->with('success', "Konfigurasi '{$name}' berhasil dihapus.");
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Test formula evaluation (AJAX/Inertia endpoint).
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'formula' => ['required', 'array'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'days' => ['nullable', 'numeric', 'min:0'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'first_hour_multiplier' => ['nullable', 'numeric', 'min:0'],
            'subsequent_hour_multiplier' => ['nullable', 'numeric', 'min:0'],
            'holiday_overtime_multiplier' => ['nullable', 'numeric', 'min:0'],
        ]);

        $formula = $request->input('formula');
        $inputs = [
            'employee_overtime_rate' => (float) ($request->input('rate') ?? 25000),
            'overtime_days' => (float) ($request->input('days') ?? 3),
            'total_overtime_hours' => (float) ($request->input('hours') ?? 8),
            'first_hour_multiplier' => $request->filled('first_hour_multiplier') ? (float) $request->input('first_hour_multiplier') : 1.5,
            'subsequent_hour_multiplier' => $request->filled('subsequent_hour_multiplier') ? (float) $request->input('subsequent_hour_multiplier') : 2.0,
            'holiday_overtime_multiplier' => $request->filled('holiday_overtime_multiplier') ? (float) $request->input('holiday_overtime_multiplier') : 2.0,
        ];

        try {
            $testResult = $this->configService->testCalculation($formula, $inputs, $request->input('day_type'));

            return response()->json($testResult);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
