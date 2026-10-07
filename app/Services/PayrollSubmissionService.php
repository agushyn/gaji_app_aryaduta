<?php

namespace App\Services;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\PayrollApprovalEvent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class PayrollSubmissionService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Submit an entire payroll period along with its draft/changes_requested payslips for review.
     */
    public function submitPeriod(PayrollPeriod $period, User $actor, ?string $notes = null): PayrollPeriod
    {
        if (! in_array($period->status, [
            PayrollPeriodStatus::DRAFT->value,
            PayrollPeriodStatus::CHANGES_REQUESTED->value,
        ], true)) {
            throw new DomainException("Periode dengan status '{$period->status_label}' tidak dapat diajukan untuk review.");
        }

        $payslipsCount = $period->payslips()->count();
        if ($payslipsCount === 0) {
            throw new DomainException('Tidak dapat mengajukan periode gaji yang belum memiliki slip gaji.');
        }

        return DB::transaction(function () use ($period, $actor, $notes, $payslipsCount) {
            $now = now();

            // 1. Update period status to in_review
            $period->update([
                'status' => PayrollPeriodStatus::IN_REVIEW->value,
                'submitted_by' => $actor->id,
                'submitted_at' => $now,
            ]);

            // 2. Batch update eligible payslips to pending_review
            $period->payslips()
                ->whereIn('status', [
                    PayslipStatus::DRAFT->value,
                    PayslipStatus::CHANGES_REQUESTED->value,
                ])
                ->update([
                    'status' => PayslipStatus::PENDING_REVIEW->value,
                    'submitted_by' => $actor->id,
                    'submitted_at' => $now,
                ]);

            // 3. Record approval event
            PayrollApprovalEvent::create([
                'payroll_period_id' => $period->id,
                'payslip_id' => null,
                'actor_user_id' => $actor->id,
                'decision' => 'submitted',
                'comment' => $notes,
                'created_at' => $now,
            ]);

            // 4. Audit log
            $this->auditLogService->log(
                action: 'payroll_period.submitted',
                subject: $period,
                changes: [
                    'period_code' => $period->period_code,
                    'status' => 'in_review',
                    'submitted_by' => $actor->name,
                    'total_slips' => $payslipsCount,
                    'notes' => $notes,
                ]
            );

            return $period->fresh(['payslips', 'submitter', 'approvalEvents.actor']);
        });
    }

    /**
     * Submit an individual payslip for review.
     */
    public function submitPayslip(Payslip $payslip, User $actor, ?string $notes = null): Payslip
    {
        if (! in_array($payslip->status, [
            PayslipStatus::DRAFT->value,
            PayslipStatus::CHANGES_REQUESTED->value,
        ], true)) {
            throw new DomainException("Slip gaji dengan status '{$payslip->status_label}' tidak dapat diajukan untuk review.");
        }

        return DB::transaction(function () use ($payslip, $actor, $notes) {
            $now = now();
            $period = $payslip->payrollPeriod;

            // 1. Update payslip status to pending_review
            $payslip->update([
                'status' => PayslipStatus::PENDING_REVIEW->value,
                'submitted_by' => $actor->id,
                'submitted_at' => $now,
            ]);

            // 2. If parent period is draft or changes_requested, transition to in_review
            if (in_array($period->status, [
                PayrollPeriodStatus::DRAFT->value,
                PayrollPeriodStatus::CHANGES_REQUESTED->value,
            ], true)) {
                $period->update([
                    'status' => PayrollPeriodStatus::IN_REVIEW->value,
                    'submitted_by' => $actor->id,
                    'submitted_at' => $now,
                ]);
            }

            // 3. Record approval event
            PayrollApprovalEvent::create([
                'payroll_period_id' => $period->id,
                'payslip_id' => $payslip->id,
                'actor_user_id' => $actor->id,
                'decision' => 'submitted',
                'comment' => $notes,
                'created_at' => $now,
            ]);

            // 4. Audit log
            $this->auditLogService->log(
                action: 'payslip.submitted',
                subject: $payslip,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'employee' => $payslip->employee_name_snapshot,
                    'status' => 'pending_review',
                    'submitted_by' => $actor->name,
                    'notes' => $notes,
                ]
            );

            return $payslip->fresh(['employee', 'payrollPeriod', 'approvalEvents.actor']);
        });
    }
}
