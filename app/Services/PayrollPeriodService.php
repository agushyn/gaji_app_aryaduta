<?php

namespace App\Services;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollPeriodService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Create a new payroll period in draft status.
     */
    public function createPeriod(array $data): PayrollPeriod
    {
        return DB::transaction(function () use ($data) {
            $period = PayrollPeriod::create([
                'period_code' => trim($data['period_code']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => PayrollPeriodStatus::DRAFT->value,
                'created_by' => Auth::id() ?? $data['created_by'] ?? User::first()?->id ?? 1,
                'approval_comment' => ! empty($data['approval_comment']) ? trim($data['approval_comment']) : null,
            ]);

            $this->auditLogService->log(
                action: 'payroll_period.created',
                subject: $period,
                changes: [
                    'period_code' => $period->period_code,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'status' => $period->status,
                ]
            );

            return $period;
        });
    }

    /**
     * Update period details (dates, code).
     */
    public function updatePeriod(PayrollPeriod $period, array $data): PayrollPeriod
    {
        if (! $period->canBeModified()) {
            throw new DomainException("Periode dengan status '{$period->status_label}' tidak dapat diubah.");
        }

        return DB::transaction(function () use ($period, $data) {
            $original = [
                'period_code' => $period->period_code,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date' => $period->end_date?->format('Y-m-d'),
            ];

            $period->fill($data)->save();

            $changes = [];
            foreach ($data as $key => $val) {
                $old = $original[$key] ?? null;
                if ($old != $val) {
                    $changes[$key] = [
                        'from' => $old,
                        'to' => $val,
                    ];
                }
            }

            if (! empty($changes)) {
                $this->auditLogService->log(
                    action: 'payroll_period.updated',
                    subject: $period,
                    changes: $changes
                );
            }

            return $period;
        });
    }

    /**
     * Delete an empty payroll period.
     */
    public function deletePeriod(PayrollPeriod $period): void
    {
        if (! $period->isDraft()) {
            throw new DomainException("Hanya periode berstatus 'Draft' yang dapat dihapus.");
        }

        if ($period->payslips()->exists()) {
            throw new DomainException(
                "Periode {$period->period_code} masih memiliki data slip gaji. Hapus seluruh slip gaji di dalamnya terlebih dahulu sebelum menghapus periode ini."
            );
        }

        DB::transaction(function () use ($period) {
            $info = [
                'period_code' => $period->period_code,
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
            ];

            $period->delete();

            $this->auditLogService->log(
                action: 'payroll_period.deleted',
                subject: PayrollPeriod::class,
                changes: $info
            );
        });
    }
}
