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

class PayrollApprovalService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Approve an entire payroll period and all its pending_review payslips.
     */
    public function approvePeriod(PayrollPeriod $period, User $approver, ?string $comment = null): PayrollPeriod
    {
        if ($period->status !== PayrollPeriodStatus::IN_REVIEW->value) {
            throw new DomainException("Periode dengan status '{$period->status_label}' tidak dapat disetujui. Hanya periode berstatus 'Menunggu Review' yang dapat disetujui.");
        }

        $this->ensureSegregationOfDutiesForPeriod($period, $approver);

        return DB::transaction(function () use ($period, $approver, $comment) {
            $now = now();

            // 1. Update period status to approved
            $period->update([
                'status' => PayrollPeriodStatus::APPROVED->value,
                'approved_by' => $approver->id,
                'approved_at' => $now,
                'approval_comment' => $comment,
            ]);

            // 2. Batch update pending_review payslips to approved
            $period->payslips()
                ->where('status', PayslipStatus::PENDING_REVIEW->value)
                ->update([
                    'status' => PayslipStatus::APPROVED->value,
                    'approved_by' => $approver->id,
                    'approved_at' => $now,
                    'approval_comment' => $comment,
                ]);

            // 3. Record approval event
            PayrollApprovalEvent::create([
                'payroll_period_id' => $period->id,
                'payslip_id' => null,
                'actor_user_id' => $approver->id,
                'decision' => 'approved',
                'comment' => $comment,
                'created_at' => $now,
            ]);

            // 4. Audit log
            $this->auditLogService->log(
                action: 'payroll_period.approved',
                subject: $period,
                changes: [
                    'period_code' => $period->period_code,
                    'status' => 'approved',
                    'approved_by' => $approver->name,
                    'comment' => $comment,
                ]
            );

            return $period->fresh(['payslips', 'approver', 'approvalEvents.actor']);
        });
    }

    /**
     * Request changes for a payroll period and return it to changes_requested status.
     * Reason comment is strictly required.
     */
    public function requestPeriodChanges(PayrollPeriod $period, User $approver, string $reason): PayrollPeriod
    {
        $trimmedReason = trim($reason);
        if (empty($trimmedReason)) {
            throw new DomainException('Alasan permintaan perbaikan wajib diisi.');
        }

        if ($period->status !== PayrollPeriodStatus::IN_REVIEW->value) {
            throw new DomainException("Periode dengan status '{$period->status_label}' tidak sedang dalam tahap review.");
        }

        return DB::transaction(function () use ($period, $approver, $trimmedReason) {
            $now = now();

            // 1. Update period status to changes_requested
            $period->update([
                'status' => PayrollPeriodStatus::CHANGES_REQUESTED->value,
                'approval_comment' => $trimmedReason,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            // 2. Update pending payslips in this period to changes_requested
            $period->payslips()
                ->where('status', PayslipStatus::PENDING_REVIEW->value)
                ->update([
                    'status' => PayslipStatus::CHANGES_REQUESTED->value,
                    'approval_comment' => $trimmedReason,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

            // 3. Record approval event
            PayrollApprovalEvent::create([
                'payroll_period_id' => $period->id,
                'payslip_id' => null,
                'actor_user_id' => $approver->id,
                'decision' => 'changes_requested',
                'comment' => $trimmedReason,
                'created_at' => $now,
            ]);

            // 4. Audit log
            $this->auditLogService->log(
                action: 'payroll_period.changes_requested',
                subject: $period,
                changes: [
                    'period_code' => $period->period_code,
                    'status' => 'changes_requested',
                    'requested_by' => $approver->name,
                    'reason' => $trimmedReason,
                ]
            );

            return $period->fresh(['payslips', 'approvalEvents.actor']);
        });
    }

    /**
     * Approve an individual payslip.
     */
    public function approvePayslip(Payslip $payslip, User $approver, ?string $comment = null): Payslip
    {
        if ($payslip->status !== PayslipStatus::PENDING_REVIEW->value) {
            throw new DomainException("Slip gaji dengan status '{$payslip->status_label}' tidak dapat disetujui. Hanya slip berstatus 'Menunggu Review' yang dapat disetujui.");
        }

        $this->ensureSegregationOfDutiesForPayslip($payslip, $approver);

        return DB::transaction(function () use ($payslip, $approver, $comment) {
            $now = now();
            $period = $payslip->payrollPeriod;

            // 1. Update payslip status to approved
            $payslip->update([
                'status' => PayslipStatus::APPROVED->value,
                'approved_by' => $approver->id,
                'approved_at' => $now,
                'approval_comment' => $comment,
            ]);

            // 2. Record approval event
            PayrollApprovalEvent::create([
                'payroll_period_id' => $period->id,
                'payslip_id' => $payslip->id,
                'actor_user_id' => $approver->id,
                'decision' => 'approved',
                'comment' => $comment,
                'created_at' => $now,
            ]);

            // 3. If all payslips in this period are now approved, auto-approve the parent period
            $pendingOrDraftCount = $period->payslips()
                ->where('status', '!=', PayslipStatus::APPROVED->value)
                ->count();

            if ($pendingOrDraftCount === 0 && $period->status === PayrollPeriodStatus::IN_REVIEW->value) {
                $period->update([
                    'status' => PayrollPeriodStatus::APPROVED->value,
                    'approved_by' => $approver->id,
                    'approved_at' => $now,
                    'approval_comment' => 'Seluruh slip gaji telah disetujui.',
                ]);

                PayrollApprovalEvent::create([
                    'payroll_period_id' => $period->id,
                    'payslip_id' => null,
                    'actor_user_id' => $approver->id,
                    'decision' => 'approved',
                    'comment' => 'Seluruh slip gaji dalam periode ini telah disetujui.',
                    'created_at' => $now,
                ]);
            }

            // 4. Audit log
            $this->auditLogService->log(
                action: 'payslip.approved',
                subject: $payslip,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'employee' => $payslip->employee_name_snapshot,
                    'status' => 'approved',
                    'approved_by' => $approver->name,
                    'comment' => $comment,
                ]
            );

            return $payslip->fresh(['employee', 'payrollPeriod', 'approvalEvents.actor']);
        });
    }

    /**
     * Request changes for an individual payslip.
     * Reason comment is strictly required.
     */
    public function requestPayslipChanges(Payslip $payslip, User $approver, string $reason): Payslip
    {
        $trimmedReason = trim($reason);
        if (empty($trimmedReason)) {
            throw new DomainException('Alasan permintaan perbaikan wajib diisi.');
        }

        if ($payslip->status !== PayslipStatus::PENDING_REVIEW->value) {
            throw new DomainException("Slip gaji dengan status '{$payslip->status_label}' tidak sedang dalam tahap review.");
        }

        return DB::transaction(function () use ($payslip, $approver, $trimmedReason) {
            $now = now();
            $period = $payslip->payrollPeriod;

            // 1. Update payslip status to changes_requested
            $payslip->update([
                'status' => PayslipStatus::CHANGES_REQUESTED->value,
                'approval_comment' => $trimmedReason,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            // 2. Cascade parent period to changes_requested if it was in_review or approved
            if (in_array($period->status, [
                PayrollPeriodStatus::IN_REVIEW->value,
                PayrollPeriodStatus::APPROVED->value,
            ], true)) {
                $period->update([
                    'status' => PayrollPeriodStatus::CHANGES_REQUESTED->value,
                    'approval_comment' => "Perbaikan diminta untuk slip {$payslip->slip_number}: {$trimmedReason}",
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
            }

            // 3. Record approval event
            PayrollApprovalEvent::create([
                'payroll_period_id' => $period->id,
                'payslip_id' => $payslip->id,
                'actor_user_id' => $approver->id,
                'decision' => 'changes_requested',
                'comment' => $trimmedReason,
                'created_at' => $now,
            ]);

            // 4. Audit log
            $this->auditLogService->log(
                action: 'payslip.changes_requested',
                subject: $payslip,
                changes: [
                    'slip_number' => $payslip->slip_number,
                    'employee' => $payslip->employee_name_snapshot,
                    'status' => 'changes_requested',
                    'requested_by' => $approver->name,
                    'reason' => $trimmedReason,
                ]
            );

            return $payslip->fresh(['employee', 'payrollPeriod', 'approvalEvents.actor']);
        });
    }

    /**
     * Enforce segregation of duties check for payroll period approval.
     */
    protected function ensureSegregationOfDutiesForPeriod(PayrollPeriod $period, User $approver): void
    {
        if (! config('payroll.enforce_segregation_of_duties', true)) {
            return;
        }

        if ($approver->id === (int) $period->created_by || $approver->id === (int) $period->submitted_by) {
            throw new DomainException('Pemisahan tugas aktif: Pengguna yang membuat atau mengajukan periode tidak dapat menyetujui pengajuannya sendiri.');
        }
    }

    /**
     * Enforce segregation of duties check for payslip approval.
     */
    protected function ensureSegregationOfDutiesForPayslip(Payslip $payslip, User $approver): void
    {
        if (! config('payroll.enforce_segregation_of_duties', true)) {
            return;
        }

        if ($approver->id === (int) $payslip->created_by || $approver->id === (int) $payslip->submitted_by) {
            throw new DomainException('Pemisahan tugas aktif: Pengguna yang membuat atau mengajukan slip tidak dapat menyetujui pengajuannya sendiri.');
        }
    }
}
