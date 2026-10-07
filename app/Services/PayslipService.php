<?php

namespace App\Services;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollApprovalEvent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayslipService
{
    public function __construct(
        protected PayrollCalculationService $calculationService,
        protected AuditLogService $auditLogService,
        protected OvertimeCalculationService $overtimeService
    ) {}

    /**
     * Create a new draft payslip for an employee within a period, with dynamic items and snapshots.
     *
     * @param  array{employee_id: int, items: array}  $data
     */
    public function createPayslip(PayrollPeriod $period, array $data): Payslip
    {
        if (! $period->canBeModified()) {
            throw new DomainException("Tidak dapat menambahkan slip ke periode dengan status '{$period->status_label}'.");
        }

        $employee = Employee::with(['office', 'division', 'position'])->findOrFail($data['employee_id']);

        // Enforce uniqueness constraint at service level
        $existing = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing) {
            throw new DomainException(
                "Karyawan {$employee->full_name} ({$employee->employee_number}) sudah memiliki slip gaji ({$existing->slip_number}) pada periode {$period->period_code}."
            );
        }

        return DB::transaction(function () use ($period, $employee, $data) {
            $rawItems = $data['items'] ?? [];
            $preparedItems = $this->prepareItems($rawItems);

            // Calculate totals strictly in backend using decimal arithmetic
            $totals = $this->calculationService->calculate($preparedItems);

            $slipNumber = "SLIP/{$period->period_code}/{$employee->employee_number}";

            // Ensure unique slip_number
            $counter = 1;
            $baseSlipNumber = $slipNumber;
            while (Payslip::where('slip_number', $slipNumber)->exists()) {
                $slipNumber = "{$baseSlipNumber}-{$counter}";
                $counter++;
            }

            // Create header with employee and period snapshot
            $defaultCompanyName = CompanySetting::get('company_name', 'CV. BINA PUSTAKA');
            $defaultCompanyAddress = CompanySetting::get('company_address', 'Jl. M. Nasir, Bendungan Kec. Cilodong - Kel. Cilodong, Depok.');
            $signatoryPersonaliaName = CompanySetting::get('signatory_personalia_name', 'Singgih Sugiarto');
            $signatoryPersonaliaTitle = CompanySetting::get('signatory_personalia_title', 'Personalia');
            $signatoryRelieverName = CompanySetting::get('signatory_reliever_name', 'Sheren Regiana');
            $signatoryRelieverTitle = CompanySetting::get('signatory_reliever_title', 'Yang Menyerahkan');

            $office = $employee->office;
            $officeName = $office?->name ?? $defaultCompanyName;
            $officeCode = $office?->code ?? 'BP';
            $officeAddress = $office?->address ?? $defaultCompanyAddress;
            $officePhone = $office?->phone ?? null;
            $officeEmail = $office?->email ?? null;

            $divisionName = $employee->division?->name ?? (is_string($employee->department) ? $employee->department : ($employee->department?->name ?? '-'));
            $positionName = ($employee->position instanceof \App\Models\Position ? $employee->position->name : $employee->position) ?: '-';

            $payslip = Payslip::create([
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
                'office_id' => $employee->office_id,
                'slip_number' => $slipNumber,
                'employee_number_snapshot' => $employee->employee_number,
                'employee_name_snapshot' => $employee->full_name,
                'department_snapshot' => $divisionName,
                'position_snapshot' => $positionName,
                'email_snapshot' => $employee->email,
                'phone_snapshot' => $employee->phone,
                'working_days_snapshot' => $data['working_days'] ?? $period->working_days ?? (int) CompanySetting::get('default_working_days', 25),
                'period_month_snapshot' => $data['period_month_label'] ?? $period->period_month_label ?? ($period->start_date ? $period->start_date->translatedFormat('F Y') : $period->period_code),
                'period_start_date_snapshot' => $period->start_date,
                'period_end_date_snapshot' => $period->end_date,
                'overtime_period_snapshot' => $data['overtime_period'] ?? null,
                'basic_salary' => $totals['basic_salary'],
                'total_earnings' => $totals['total_earnings'],
                'total_deductions' => $totals['total_deductions'],
                'net_salary' => $totals['net_salary'],
                'gaji_bruto' => $totals['gaji_bruto'],
                'uang_dinas' => $totals['uang_dinas'],
                'company_name_snapshot' => $officeName,
                'company_address_snapshot' => $officeAddress,
                'office_name_snapshot' => $officeName,
                'office_code_snapshot' => $officeCode,
                'office_address_snapshot' => $officeAddress,
                'office_phone_snapshot' => $officePhone,
                'office_email_snapshot' => $officeEmail,
                'division_name_snapshot' => $divisionName,
                'position_name_snapshot' => $positionName,
                'signatory_personalia_name' => $signatoryPersonaliaName,
                'signatory_personalia_title' => $signatoryPersonaliaTitle,
                'signatory_reliever_name' => $signatoryRelieverName,
                'signatory_reliever_title' => $signatoryRelieverTitle,
                'signatory_receiver_name' => $employee->full_name,
                'currency' => 'IDR',
                'status' => PayslipStatus::DRAFT->value,
                'created_by' => Auth::id() ?? $data['created_by'] ?? User::first()?->id ?? 1,
            ]);

            // Save items with snapshot descriptions, codes, detail rates, and calculation snapshots
            foreach ($preparedItems as $order => $itemData) {
                $payslip->items()->create([
                    'pay_component_id' => $itemData['pay_component_id'] ?? null,
                    'calculation_config_id' => $itemData['calculation_config_id'] ?? null,
                    'calculation_config_version' => $itemData['calculation_config_version'] ?? null,
                    'calculation_snapshot' => $itemData['calculation_snapshot'] ?? null,
                    'item_code_snapshot' => $itemData['item_code_snapshot'],
                    'description_snapshot' => $itemData['description_snapshot'],
                    'item_type' => $itemData['item_type'],
                    'category' => $itemData['category'] ?? null,
                    'quantity' => $itemData['quantity'] ?? null,
                    'unit' => $itemData['unit'] ?? null,
                    'rate' => $itemData['rate'] ?? null,
                    'note' => $itemData['note'] ?? null,
                    'amount' => $itemData['amount'],
                    'sort_order' => $order + 1,
                ]);
            }

            $this->auditLogService->log(
                action: 'payslip.created',
                subject: $payslip,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'employee' => $payslip->employee_name_snapshot,
                    'period' => $period->period_code,
                    'net_salary' => $payslip->net_salary,
                    'gaji_bruto' => $payslip->gaji_bruto,
                    'uang_dinas' => $payslip->uang_dinas,
                    'items_count' => count($preparedItems),
                ]
            );

            return $payslip->load(['items', 'employee', 'payrollPeriod']);
        });
    }

    /**
     * Update an existing payslip and recalculate its items and totals.
     * Modifying a submitted or approved payslip will automatically invalidate
     * previous approvals and revert the payslip (and parent period) to draft for resubmission.
     */
    public function updatePayslip(Payslip $payslip, array $data): Payslip
    {
        if (! $payslip->canBeModified()) {
            throw new DomainException("Slip gaji dengan status '{$payslip->status_label}' tidak dapat diubah.");
        }

        return DB::transaction(function () use ($payslip, $data) {
            $rawItems = $data['items'] ?? [];
            $preparedItems = $this->prepareItems($rawItems);

            // Recompute totals in backend
            $totals = $this->calculationService->calculate($preparedItems);

            $previousStatus = $payslip->status;
            $wasSubmittedOrApproved = in_array($previousStatus, [
                PayslipStatus::PENDING_REVIEW->value,
                PayslipStatus::APPROVED->value,
            ], true);

            $actorId = Auth::id() ?? $data['updated_by'] ?? $payslip->created_by ?? 1;

            $updatePayload = [
                'basic_salary' => $totals['basic_salary'],
                'total_earnings' => $totals['total_earnings'],
                'total_deductions' => $totals['total_deductions'],
                'net_salary' => $totals['net_salary'],
                'gaji_bruto' => $totals['gaji_bruto'],
                'uang_dinas' => $totals['uang_dinas'],
            ];

            if (isset($data['working_days'])) {
                $updatePayload['working_days_snapshot'] = $data['working_days'];
            }
            if (isset($data['overtime_period'])) {
                $updatePayload['overtime_period_snapshot'] = $data['overtime_period'];
            }

            // If modifying after submit or approval, invalidate prior approvals and revert to draft
            if ($wasSubmittedOrApproved) {
                $updatePayload['status'] = PayslipStatus::DRAFT->value;
                $updatePayload['approved_by'] = null;
                $updatePayload['approved_at'] = null;
                $updatePayload['approval_comment'] = null;
                $updatePayload['submitted_by'] = null;
                $updatePayload['submitted_at'] = null;
            }

            // Update header totals and lifecycle
            $payslip->update($updatePayload);

            // Replace items atomically
            $payslip->items()->delete();

            foreach ($preparedItems as $order => $itemData) {
                $payslip->items()->create([
                    'pay_component_id' => $itemData['pay_component_id'] ?? null,
                    'calculation_config_id' => $itemData['calculation_config_id'] ?? null,
                    'calculation_config_version' => $itemData['calculation_config_version'] ?? null,
                    'calculation_snapshot' => $itemData['calculation_snapshot'] ?? null,
                    'item_code_snapshot' => $itemData['item_code_snapshot'],
                    'description_snapshot' => $itemData['description_snapshot'],
                    'item_type' => $itemData['item_type'],
                    'category' => $itemData['category'] ?? null,
                    'quantity' => $itemData['quantity'] ?? null,
                    'unit' => $itemData['unit'] ?? null,
                    'rate' => $itemData['rate'] ?? null,
                    'note' => $itemData['note'] ?? null,
                    'amount' => $itemData['amount'],
                    'sort_order' => $order + 1,
                ]);
            }

            // If prior approval was invalidated, log approval events and revert parent period if needed
            if ($wasSubmittedOrApproved) {
                PayrollApprovalEvent::create([
                    'payroll_period_id' => $payslip->payroll_period_id,
                    'payslip_id' => $payslip->id,
                    'actor_user_id' => $actorId,
                    'decision' => 'reopened',
                    'comment' => "Data rincian slip {$payslip->slip_number} diperbarui. Persetujuan sebelumnya dibatalkan otomatis dan status dikembalikan ke Draft.",
                    'created_at' => now(),
                ]);

                $period = $payslip->payrollPeriod;
                if (in_array($period->status, [
                    PayrollPeriodStatus::IN_REVIEW->value,
                    PayrollPeriodStatus::APPROVED->value,
                ], true)) {
                    $period->update([
                        'status' => PayrollPeriodStatus::DRAFT->value,
                        'approved_by' => null,
                        'approved_at' => null,
                        'approval_comment' => null,
                        'submitted_by' => null,
                        'submitted_at' => null,
                    ]);

                    PayrollApprovalEvent::create([
                        'payroll_period_id' => $period->id,
                        'payslip_id' => null,
                        'actor_user_id' => $actorId,
                        'decision' => 'reopened',
                        'comment' => "Perubahan pada slip {$payslip->slip_number} membatalkan persetujuan periode {$period->period_code}. Status dikembalikan ke Draft untuk pengajuan ulang.",
                        'created_at' => now(),
                    ]);
                }
            }

            $this->auditLogService->log(
                action: $wasSubmittedOrApproved ? 'payslip.updated_and_reopened' : 'payslip.updated',
                subject: $payslip,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'previous_status' => $previousStatus,
                    'new_status' => $payslip->status,
                    'net_salary' => $payslip->net_salary,
                    'total_earnings' => $payslip->total_earnings,
                    'total_deductions' => $payslip->total_deductions,
                    'items_count' => count($preparedItems),
                ]
            );

            return $payslip->fresh(['items', 'employee', 'payrollPeriod']);
        });
    }

    /**
     * Delete a draft payslip.
     */
    public function deletePayslip(Payslip $payslip): void
    {
        if (! $payslip->canBeModified()) {
            throw new DomainException("Hanya slip gaji berstatus 'Draft' atau 'Perlu Revisi' yang dapat dihapus.");
        }

        DB::transaction(function () use ($payslip) {
            $info = [
                'slip_number' => $payslip->slip_number,
                'employee' => $payslip->employee_name_snapshot,
                'period_id' => $payslip->payroll_period_id,
            ];

            $payslip->items()->delete();
            $payslip->delete();

            $this->auditLogService->log(
                action: 'payslip.deleted',
                subject: Payslip::class,
                changes: $info
            );
        });
    }

    /**
     * Void a finalized payslip with recorded actor, reason, and timestamp.
     */
    public function voidPayslip(Payslip $payslip, User $actor, string $reason): Payslip
    {
        if ($payslip->status !== PayslipStatus::FINALIZED->value) {
            throw new DomainException("Hanya slip gaji berstatus 'Final' yang dapat dibatalkan (void).");
        }

        $reason = trim($reason);
        if (empty($reason)) {
            throw new DomainException('Alasan pembatalan (void) wajib diisi.');
        }

        return DB::transaction(function () use ($payslip, $actor, $reason) {
            $payslip->lockForUpdate();

            $payslip->update([
                'status' => PayslipStatus::VOIDED->value,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            PayrollApprovalEvent::create([
                'payroll_period_id' => $payslip->payroll_period_id,
                'payslip_id' => $payslip->id,
                'actor_user_id' => $actor->id,
                'decision' => 'voided',
                'comment' => "Slip gaji {$payslip->slip_number} dibatalkan (void). Alasan: {$reason}",
                'created_at' => now(),
            ]);

            $this->auditLogService->log(
                action: 'payslip.voided',
                subject: $payslip,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'status' => 'voided',
                    'voided_by' => $actor->name,
                    'reason' => $reason,
                ]
            );

            return $payslip->fresh(['employee', 'payrollPeriod', 'voidedBy']);
        });
    }

    /**
     * Auto generate payslips for all active employees for a given payroll period.
     * Idempotent: Skips finalized or voided payslips.
     * For existing draft or changes_requested payslips, updates master salary components
     * while preserving other dynamic components (such as overtime, travel, loans, etc.).
     *
     * @return array{created: int, updated: int, skipped: int, total_processed: int}
     */
    public function generatePayrollForPeriod(PayrollPeriod $period, ?int $actorId = null): array
    {
        if (! $period->canBeModified()) {
            throw new DomainException("Tidak dapat melakukan generate payroll untuk periode dengan status '{$period->status_label}'.");
        }

        $actorId = $actorId ?? Auth::id() ?? 1;

        return DB::transaction(function () use ($period, $actorId) {
            $workingDays = (int) ($period->working_days ?: 25);
            $employees = Employee::with(['office', 'division', 'position'])
                ->where('employment_status', 'active')
                ->orderBy('employee_number')
                ->get();

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;

            $masterCodes = [
                'BASIC_SALARY',
                'POSITION_ALLOWANCE',
                'ATTENDANCE_INCENTIVE',
                'FAMILY_ALLOWANCE',
                'MEAL_ALLOWANCE',
                'TRANSPORT_ALLOWANCE',
                'BPJS_TK',
            ];

            foreach ($employees as $employee) {
                $existing = Payslip::with('items')
                    ->where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->first();

                // If payslip is finalized or voided, skip to preserve historical integrity
                if ($existing && ! $existing->canBeModified()) {
                    $skippedCount++;

                    continue;
                }

                // Build default master salary items for this employee
                $masterItems = $employee->getDefaultSalaryItems($workingDays);

                if ($existing) {
                    // Update existing draft / changes_requested payslip:
                    // Keep non-master dynamic items (e.g., OVERTIME, OFFICIAL_DUTY, LOAN_DEDUCTION, etc.)
                    $preservedDynamicItems = [];
                    foreach ($existing->items as $item) {
                        $code = strtoupper($item->item_code_snapshot ?? '');
                        if (! in_array($code, $masterCodes, true)) {
                            $preservedDynamicItems[] = [
                                'pay_component_id' => $item->pay_component_id,
                                'item_code_snapshot' => $item->item_code_snapshot,
                                'description_snapshot' => $item->description_snapshot,
                                'item_type' => $item->item_type,
                                'category' => $item->category,
                                'quantity' => $item->quantity,
                                'unit' => $item->unit,
                                'rate' => $item->rate,
                                'note' => $item->note,
                                'amount' => $item->amount,
                            ];
                        }
                    }

                    // Merge master items + preserved dynamic items
                    $combinedItems = array_merge($masterItems, $preservedDynamicItems);

                    // Re-calculate & update existing payslip
                    $this->updatePayslip($existing, [
                        'working_days' => $workingDays,
                        'items' => $combinedItems,
                        'updated_by' => $actorId,
                    ]);

                    $updatedCount++;
                } else {
                    // Create new payslip
                    $this->createPayslip($period, [
                        'employee_id' => $employee->id,
                        'working_days' => $workingDays,
                        'items' => $masterItems,
                        'created_by' => $actorId,
                    ]);

                    $createdCount++;
                }
            }

            $this->auditLogService->log(
                action: 'payroll_period.auto_generated',
                subject: $period,
                changes: [
                    'working_days' => $workingDays,
                    'created_slips' => $createdCount,
                    'updated_slips' => $updatedCount,
                    'skipped_slips' => $skippedCount,
                    'total_employees' => $employees->count(),
                ]
            );

            return [
                'created' => $createdCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'total_processed' => $employees->count(),
            ];
        });
    }

    /**
     * Normalize items with component data snapshots.
     */
    protected function prepareItems(array $rawItems): array
    {
        $prepared = [];
        $components = PayComponent::all()->keyBy('id');

        foreach ($rawItems as $index => $item) {
            $componentId = $item['pay_component_id'] ?? null;
            $component = $componentId ? $components->get($componentId) : null;

            $code = $component ? $component->code : ($item['item_code_snapshot'] ?? 'ITEM-'.($index + 1));
            $name = $component ? $component->name : ($item['description_snapshot'] ?? 'Komponen '.($index + 1));
            $type = $component ? $component->type : ($item['item_type'] ?? 'earning');

            $amount = $this->calculationService->normalizeDecimal($item['amount'] ?? 0);
            $quantity = isset($item['quantity']) && $item['quantity'] !== '' ? $this->calculationService->normalizeDecimal($item['quantity']) : null;
            $rate = isset($item['rate']) && $item['rate'] !== '' ? $this->calculationService->normalizeDecimal($item['rate']) : null;

            $prepared[] = [
                'pay_component_id' => $component?->id,
                'calculation_config_id' => $item['calculation_config_id'] ?? null,
                'calculation_config_version' => $item['calculation_config_version'] ?? null,
                'calculation_snapshot' => $item['calculation_snapshot'] ?? null,
                'item_code_snapshot' => strtoupper(trim($code)),
                'description_snapshot' => trim($name),
                'item_type' => strtolower(trim($type)),
                'category' => ! empty($item['category']) ? trim($item['category']) : null,
                'quantity' => $quantity,
                'unit' => ! empty($item['unit']) ? trim($item['unit']) : null,
                'rate' => $rate,
                'note' => ! empty($item['note']) ? trim($item['note']) : null,
                'amount' => $amount,
            ];
        }

        return $prepared;
    }

    /**
     * Helper to compute an overtime item for an employee with full snapshot trace.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function calculateOvertimeItem(Employee $employee, array $params = []): array
    {
        $result = $this->overtimeService->calculateForEmployee($employee, $params);
        $overtimeComp = PayComponent::where('code', 'OVERTIME')->first();

        return [
            'pay_component_id' => $overtimeComp?->id,
            'item_code_snapshot' => 'OVERTIME',
            'description_snapshot' => $overtimeComp?->name ?? 'Uang Lembur',
            'item_type' => 'earning',
            'quantity' => $result['quantity'],
            'unit' => 'jam',
            'rate' => $result['rate'],
            'amount' => $result['amount'],
            'calculation_config_id' => $result['snapshot']['working_day']['config_id'] ?? null,
            'calculation_config_version' => $result['snapshot']['working_day']['config_version'] ?? 1,
            'calculation_snapshot' => $result['snapshot'],
        ];
    }
}
