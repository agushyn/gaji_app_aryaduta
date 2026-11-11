<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

class AttendanceDeductionService
{
    public const SETTING_ENABLED = 'attendance_deduction_enabled';
    public const SETTING_COMPONENT_IDS = 'attendance_deduction_component_ids';

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Check whether attendance deduction calculation is currently active.
     */
    public function isEnabled(): bool
    {
        return CompanySetting::get(self::SETTING_ENABLED, '0') === '1';
    }

    /**
     * Get IDs of pay components configured for attendance deduction.
     * Defaults to MEAL_ALLOWANCE and TRANSPORT_ALLOWANCE if not explicitly configured.
     *
     * @return array<int>
     */
    public function getSelectedComponentIds(): array
    {
        $raw = CompanySetting::get(self::SETTING_COMPONENT_IDS);

        if ($raw !== null && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_filter(array_map('intval', $decoded))));
            }
        }

        // Default initial components: Meal Allowance & Transport Allowance
        return PayComponent::where('type', 'earning')
            ->where('is_active', true)
            ->whereIn('code', ['MEAL_ALLOWANCE', 'TRANSPORT_ALLOWANCE'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * Get full configuration state for UI.
     */
    public function getConfig(): array
    {
        $enabled = $this->isEnabled();
        $selectedIds = $this->getSelectedComponentIds();

        return [
            'is_active' => $enabled,
            'component_ids' => $selectedIds,
        ];
    }

    /**
     * Update attendance deduction configuration and record audit log.
     *
     * @param  array{is_active: bool, component_ids: array<int>}  $data
     */
    public function updateConfig(array $data, ?User $actor = null): array
    {
        $isActive = (bool) ($data['is_active'] ?? false);
        $rawIds = $data['component_ids'] ?? [];

        // Validate that all provided component IDs are valid active earning components
        $validComponentIds = PayComponent::where('type', 'earning')
            ->where('is_active', true)
            ->whereIn('id', $rawIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        CompanySetting::set(self::SETTING_ENABLED, $isActive ? '1' : '0');
        CompanySetting::set(self::SETTING_COMPONENT_IDS, json_encode($validComponentIds));

        $this->auditLogService->log(
            action: 'payroll_config.attendance_deduction_updated',
            subject: CompanySetting::class,
            changes: [
                'is_active' => $isActive,
                'component_ids' => $validComponentIds,
                'updated_by' => $actor?->name ?? 'System',
            ]
        );

        return [
            'is_active' => $isActive,
            'component_ids' => $validComponentIds,
        ];
    }

    /**
     * Get list of earning components eligible for absence deduction with descriptive metadata.
     */
    public function getDeductiblePayComponents(): Collection
    {
        return PayComponent::where('type', 'earning')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($comp) {
                $categoryLabel = match ($comp->code) {
                    'MEAL_ALLOWANCE', 'TRANSPORT_ALLOWANCE' => 'Pendapatan Berdasarkan Hari (Daily)',
                    default => 'Pendapatan Tetap Bulanan (Monthly Fixed)',
                };

                $description = match ($comp->code) {
                    'MEAL_ALLOWANCE' => 'Tarif uang makan per hari (master karyawan)',
                    'TRANSPORT_ALLOWANCE' => 'Tarif uang transport per hari (master karyawan)',
                    'BASIC_SALARY' => 'Gaji pokok bulanan dibagi jumlah hari kerja periode',
                    'POSITION_ALLOWANCE' => 'Tunjangan jabatan bulanan dibagi jumlah hari kerja periode',
                    'ATTENDANCE_INCENTIVE' => 'Tunjangan kehadiran bulanan dibagi jumlah hari kerja periode',
                    'FAMILY_ALLOWANCE' => 'Tunjangan keluarga bulanan dibagi jumlah hari kerja periode',
                    'FUNCTIONAL_ALLOWANCE' => 'Tunjangan fungsional bulanan dibagi jumlah hari kerja periode',
                    'COMMUNICATION_ALLOWANCE' => 'Tunjangan pulsa bulanan dibagi jumlah hari kerja periode',
                    'HEALTH_ALLOWANCE' => 'Biaya kesehatan bulanan dibagi jumlah hari kerja periode',
                    default => 'Nilai bulanan komponen dibagi jumlah hari kerja periode',
                };

                return [
                    'id' => $comp->id,
                    'code' => $comp->code,
                    'name' => $comp->name,
                    'type' => $comp->type,
                    'category_label' => $categoryLabel,
                    'description' => $description,
                ];
            });
    }

    /**
     * Determine daily rate for a component on a given employee and payslip context.
     */
    public function resolveDailyRate(PayComponent $component, Employee $employee, int $workingDays, array $earningsItems = []): float
    {
        $code = strtoupper($component->code);
        $wDays = max(1, $workingDays);

        // 1. Check if employee has explicit daily rates
        if ($code === 'MEAL_ALLOWANCE' && bccomp((string) ($employee->uang_makan_per_hari ?? 0), '0.00', 2) > 0) {
            return (float) $employee->uang_makan_per_hari;
        }

        if ($code === 'TRANSPORT_ALLOWANCE' && bccomp((string) ($employee->uang_transportasi_per_hari ?? 0), '0.00', 2) > 0) {
            return (float) $employee->uang_transportasi_per_hari;
        }

        // 2. Check in earnings items passed for this payslip
        foreach ($earningsItems as $item) {
            $itemCode = strtoupper($item['item_code_snapshot'] ?? '');
            $itemId = $item['pay_component_id'] ?? null;

            if ($itemCode === $code || ($itemId && (int) $itemId === $component->id)) {
                // If the item explicitly has a daily rate recorded
                if (isset($item['rate']) && is_numeric($item['rate']) && (float) $item['rate'] > 0) {
                    return (float) $item['rate'];
                }

                // If monthly total amount, divide by working days
                $amount = (float) ($item['amount'] ?? 0);
                if ($amount > 0) {
                    return round($amount / $wDays, 2);
                }
            }
        }

        // 3. Fallback to employee fixed master values
        $masterAmount = match ($code) {
            'BASIC_SALARY' => (float) ($employee->gaji_pokok ?? 0),
            'POSITION_ALLOWANCE' => (float) ($employee->tunjangan_jabatan ?? 0),
            'ATTENDANCE_INCENTIVE' => (float) ($employee->tunjangan_kehadiran ?? 0),
            'FAMILY_ALLOWANCE' => (float) ($employee->tunjangan_keluarga ?? 0),
            default => 0.0,
        };

        if ($masterAmount > 0) {
            return round($masterAmount / $wDays, 2);
        }

        return 0.0;
    }

    /**
     * Calculate attendance deduction items for an employee.
     *
     * @param  array<array<string, mixed>>  $earningsItems
     * @return array{items: array<array<string, mixed>>, total_deduction: float}
     */
    public function calculateDeductions(
        Employee $employee,
        float $absentDays,
        int $workingDays,
        array $earningsItems = []
    ): array {
        if ($absentDays <= 0 || ! $this->isEnabled()) {
            return [
                'items' => [],
                'total_deduction' => 0.0,
            ];
        }

        $selectedIds = $this->getSelectedComponentIds();
        if (empty($selectedIds)) {
            return [
                'items' => [],
                'total_deduction' => 0.0,
            ];
        }

        $components = PayComponent::where('type', 'earning')
            ->where('is_active', true)
            ->whereIn('id', $selectedIds)
            ->get();

        $items = [];
        $totalDeduction = 0.0;

        foreach ($components as $comp) {
            $dailyRate = $this->resolveDailyRate($comp, $employee, $workingDays, $earningsItems);

            if ($dailyRate > 0) {
                $cutAmount = round($dailyRate * $absentDays, 2);

                if ($cutAmount > 0) {
                    $totalDeduction += $cutAmount;

                    $items[] = [
                        'pay_component_id' => $comp->id,
                        'item_code_snapshot' => 'ATT_CUT_' . $comp->code,
                        'description_snapshot' => 'Potongan Kehadiran: ' . $comp->name,
                        'item_type' => 'deduction',
                        'category' => 'attendance_deduction',
                        'quantity' => $absentDays,
                        'unit' => 'hari',
                        'rate' => $dailyRate,
                        'amount' => $cutAmount,
                        'note' => "Potongan {$comp->name} ({$absentDays} hari @ Rp " . number_format($dailyRate, 0, ',', '.') . ')',
                        'calculation_snapshot' => [
                            'rule' => 'attendance_deduction',
                            'component_id' => $comp->id,
                            'component_code' => $comp->code,
                            'component_name' => $comp->name,
                            'daily_rate' => $dailyRate,
                            'absent_days' => $absentDays,
                            'amount' => $cutAmount,
                            'working_days' => $workingDays,
                            'formula' => "{$dailyRate} * {$absentDays} = {$cutAmount}",
                            'calculated_at' => now()->toIso8601String(),
                        ],
                    ];
                }
            }
        }

        return [
            'items' => $items,
            'total_deduction' => round($totalDeduction, 2),
        ];
    }
}
