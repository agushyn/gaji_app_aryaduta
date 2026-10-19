<?php

namespace App\Services;

use App\Models\PayComponent;
use App\Models\PayrollCalculationConfig;
use App\Models\PayrollCalculationConfigVersion;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayrollCalculationConfigService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected FormulaEvaluatorService $formulaEvaluator
    ) {}

    /**
     * Create a new calculation configuration with version 1.
     *
     * @param  array<string, mixed>  $data
     */
    public function createConfig(array $data): PayrollCalculationConfig
    {
        return DB::transaction(function () use ($data) {
            $code = ! empty($data['code'])
                ? strtoupper(trim($data['code']))
                : strtoupper(Str::slug($data['name'], '_'));

            // Ensure unique code
            $baseCode = $code;
            $counter = 1;
            while (PayrollCalculationConfig::where('code', $code)->exists()) {
                $code = "{$baseCode}_{$counter}";
                $counter++;
            }

            $userId = Auth::id();

            $config = PayrollCalculationConfig::create([
                'name' => trim($data['name']),
                'code' => $code,
                'component_id' => $data['component_id'],
                'day_type' => $data['day_type'],
                'is_active' => $data['is_active'] ?? true,
                'current_version' => 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $version = PayrollCalculationConfigVersion::create([
                'config_id' => $config->id,
                'version' => 1,
                'formula' => $data['formula'],
                'description' => $data['description'] ?? 'Versi awal konfigurasi formula.',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $this->auditLogService->log(
                action: 'payroll_config.created',
                subject: $config,
                changes: [
                    'name' => $config->name,
                    'code' => $config->code,
                    'day_type' => $config->day_type,
                    'version' => 1,
                    'formula' => $data['formula'],
                ]
            );

            return $config;
        });
    }

    /**
     * Update an existing configuration.
     * If the formula has changed, creates a new version to preserve historical audit trail.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateConfig(PayrollCalculationConfig $config, array $data): PayrollCalculationConfig
    {
        return DB::transaction(function () use ($config, $data) {
            $userId = Auth::id();
            $oldFormula = $config->currentVersionRecord?->formula;
            $newFormula = $data['formula'] ?? null;

            $formulaChanged = $newFormula && json_encode($oldFormula) !== json_encode($newFormula);

            $oldData = [
                'name' => $config->name,
                'day_type' => $config->day_type,
                'component_id' => $config->component_id,
                'is_active' => $config->is_active,
                'version' => $config->current_version,
            ];

            $config->name = trim($data['name']);
            if (! empty($data['code'])) {
                $config->code = strtoupper(trim($data['code']));
            }
            $config->component_id = $data['component_id'];
            $config->day_type = $data['day_type'];
            if (isset($data['is_active'])) {
                $config->is_active = (bool) $data['is_active'];
            }
            $config->updated_by = $userId;

            if ($formulaChanged) {
                $nextVersionNumber = $config->current_version + 1;
                $config->current_version = $nextVersionNumber;

                // Deactivate previous versions
                $config->versions()->update(['is_active' => false]);

                // Create new version
                PayrollCalculationConfigVersion::create([
                    'config_id' => $config->id,
                    'version' => $nextVersionNumber,
                    'formula' => $newFormula,
                    'description' => $data['description'] ?? "Pembaruan ke versi {$nextVersionNumber}.",
                    'is_active' => true,
                    'created_by' => $userId,
                ]);
            }

            $config->save();

            $this->auditLogService->log(
                action: $formulaChanged ? 'payroll_config.version_created' : 'payroll_config.updated',
                subject: $config,
                changes: [
                    'old' => $oldData,
                    'new' => [
                        'name' => $config->name,
                        'day_type' => $config->day_type,
                        'component_id' => $config->component_id,
                        'is_active' => $config->is_active,
                        'version' => $config->current_version,
                        'formula_changed' => $formulaChanged,
                    ],
                ]
            );

            return $config->fresh(['versions', 'payComponent', 'currentVersionRecord']);
        });
    }

    /**
     * Duplicate an existing configuration.
     */
    public function duplicateConfig(PayrollCalculationConfig $sourceConfig): PayrollCalculationConfig
    {
        return DB::transaction(function () use ($sourceConfig) {
            $latestVersion = $sourceConfig->currentVersionRecord;
            $formula = $latestVersion ? $latestVersion->formula : [];

            $copyName = $sourceConfig->name . ' (Salinan)';
            $copyCode = $sourceConfig->code . '_COPY_' . time();

            return $this->createConfig([
                'name' => $copyName,
                'code' => $copyCode,
                'component_id' => $sourceConfig->component_id,
                'day_type' => $sourceConfig->day_type,
                'is_active' => false,
                'formula' => $formula,
                'description' => "Duplikat dari konfigurasi {$sourceConfig->name} (v{$sourceConfig->current_version})",
            ]);
        });
    }

    /**
     * Toggle active status of a configuration.
     */
    public function toggleActive(PayrollCalculationConfig $config): PayrollCalculationConfig
    {
        $config->is_active = ! $config->is_active;
        $config->updated_by = Auth::id();
        $config->save();

        $this->auditLogService->log(
            action: $config->is_active ? 'payroll_config.activated' : 'payroll_config.deactivated',
            subject: $config,
            changes: ['is_active' => $config->is_active]
        );

        return $config;
    }

    /**
     * Delete configuration if not used in finalized payslips.
     */
    public function deleteConfig(PayrollCalculationConfig $config): void
    {
        if ($config->isUsedInFinalizedPayslips()) {
            throw new DomainException('Konfigurasi perhitungan ini tidak dapat dihapus karena telah digunakan pada slip gaji berstatus Finalized. Silakan nonaktifkan konfigurasi jika tidak ingin digunakan lagi.');
        }

        DB::transaction(function () use ($config) {
            $this->auditLogService->log(
                action: 'payroll_config.deleted',
                subject: $config,
                changes: [
                    'name' => $config->name,
                    'code' => $config->code,
                ]
            );

            $config->delete();
        });
    }

    /**
     * Test formula evaluation with test inputs.
     *
     * @param  array<string, mixed>  $formula
     * @param  array<string, mixed>  $testInputs
     * @return array<string, mixed>
     */
    public function testCalculation(array $formula, array $testInputs, ?string $dayType = null): array
    {
        $evalResult = $this->formulaEvaluator->evaluate($formula, $testInputs);
        $preview = $this->formulaEvaluator->toHumanReadable($formula, $dayType);

        return [
            'success' => true,
            'result' => $evalResult['result'],
            'trace' => $evalResult['trace'],
            'steps' => $evalResult['steps'],
            'inputs' => $evalResult['inputs'],
            'preview' => $preview,
        ];
    }

    /**
     * Update overtime deduction thresholds for working day and holiday.
     * Updates active versions with new thresholds in a single database transaction,
     * maintaining audit logs and versioning history.
     *
     * @param  float|null  $workingDayThreshold
     * @param  float|null  $holidayThreshold
     * @return array{working_day: PayrollCalculationConfig|null, holiday: PayrollCalculationConfig|null}
     */
    public function updateOvertimeThresholds(?float $workingDayThreshold, ?float $holidayThreshold): array
    {
        return DB::transaction(function () use ($workingDayThreshold, $holidayThreshold) {
            $overtimeService = app(OvertimeCalculationService::class);
            $payComponent = PayComponent::firstOrCreate(
                ['code' => 'OVERTIME'],
                [
                    'name' => 'Uang Lembur',
                    'type' => 'earning',
                    'sort_order' => 40,
                    'is_active' => true,
                ]
            );

            $result = [
                'working_day' => null,
                'holiday' => null,
            ];

            // 1. Update Working Day Threshold
            if ($workingDayThreshold !== null) {
                $workingConfig = PayrollCalculationConfig::with('currentVersionRecord')
                    ->where('code', 'OVERTIME_WORKING_DAY')
                    ->first();

                if (! $workingConfig) {
                    $workingConfig = PayrollCalculationConfig::with('currentVersionRecord')
                        ->where('day_type', 'working_day')
                        ->active()
                        ->first();
                }

                $cleanWorkingThreshold = round($workingDayThreshold, 2);

                if (! $workingConfig) {
                    $initialFormula = $overtimeService->getDefaultWorkingDayFormula();
                    $initialFormula['break_threshold'] = $cleanWorkingThreshold;

                    $workingConfig = $this->createConfig([
                        'name' => 'Lembur Hari Biasa',
                        'code' => 'OVERTIME_WORKING_DAY',
                        'component_id' => $payComponent->id,
                        'day_type' => 'working_day',
                        'is_active' => true,
                        'formula' => $initialFormula,
                        'description' => "Versi 1: Konfigurasi awal dengan threshold {$cleanWorkingThreshold} jam.",
                    ]);
                } else {
                    $formula = (is_array($workingConfig->currentVersionRecord?->formula) && ! empty($workingConfig->currentVersionRecord->formula))
                        ? $workingConfig->currentVersionRecord->formula
                        : $overtimeService->getDefaultWorkingDayFormula();

                    $formula['break_threshold'] = $cleanWorkingThreshold;
                    $formula['break_duration_per_day'] = $formula['break_duration_per_day'] ?? 0.5;

                    $workingConfig = $this->updateConfig($workingConfig, [
                        'name' => $workingConfig->name,
                        'code' => $workingConfig->code,
                        'component_id' => $workingConfig->component_id,
                        'day_type' => $workingConfig->day_type,
                        'is_active' => $workingConfig->is_active,
                        'formula' => $formula,
                        'description' => "Pembaruan threshold lembur hari biasa menjadi {$cleanWorkingThreshold} jam (≥ {$cleanWorkingThreshold} jam dipotong).",
                    ]);
                }

                $result['working_day'] = $workingConfig;
            }

            // 2. Update Holiday Threshold
            if ($holidayThreshold !== null) {
                $holidayConfig = PayrollCalculationConfig::with('currentVersionRecord')
                    ->where('code', 'OVERTIME_HOLIDAY')
                    ->first();

                if (! $holidayConfig) {
                    $holidayConfig = PayrollCalculationConfig::with('currentVersionRecord')
                        ->where('day_type', 'holiday')
                        ->active()
                        ->first();
                }

                $cleanHolidayThreshold = round($holidayThreshold, 2);

                if (! $holidayConfig) {
                    $initialFormula = $overtimeService->getDefaultHolidayFormula();
                    $initialFormula['break_threshold'] = $cleanHolidayThreshold;

                    $holidayConfig = $this->createConfig([
                        'name' => 'Lembur Hari Libur',
                        'code' => 'OVERTIME_HOLIDAY',
                        'component_id' => $payComponent->id,
                        'day_type' => 'holiday',
                        'is_active' => true,
                        'formula' => $initialFormula,
                        'description' => "Versi 1: Konfigurasi awal dengan threshold {$cleanHolidayThreshold} jam.",
                    ]);
                } else {
                    $formula = (is_array($holidayConfig->currentVersionRecord?->formula) && ! empty($holidayConfig->currentVersionRecord->formula))
                        ? $holidayConfig->currentVersionRecord->formula
                        : $overtimeService->getDefaultHolidayFormula();

                    $formula['break_threshold'] = $cleanHolidayThreshold;
                    $formula['break_duration_per_day'] = $formula['break_duration_per_day'] ?? 1.0;

                    $holidayConfig = $this->updateConfig($holidayConfig, [
                        'name' => $holidayConfig->name,
                        'code' => $holidayConfig->code,
                        'component_id' => $holidayConfig->component_id,
                        'day_type' => $holidayConfig->day_type,
                        'is_active' => $holidayConfig->is_active,
                        'formula' => $formula,
                        'description' => "Pembaruan threshold lembur hari libur menjadi {$cleanHolidayThreshold} jam (≥ {$cleanHolidayThreshold} jam dipotong).",
                    ]);
                }

                $result['holiday'] = $holidayConfig;
            }

            return $result;
        });
    }
}
