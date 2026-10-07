<?php

namespace App\Policies;

use App\Enums\PayslipStatus;
use App\Models\Payslip;
use App\Models\User;

class PayslipPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payslips.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('payslips.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.manage') && $payslip->canBeModified();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.manage') && $payslip->canBeModified();
    }

    /**
     * Determine whether the user can submit the payslip for review.
     */
    public function submit(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.manage') && $payslip->canBeSubmitted();
    }

    /**
     * Determine whether the user can approve the payslip.
     */
    public function approve(User $user, Payslip $payslip): bool
    {
        if (! $user->hasPermission('approvals.decide') || ! $payslip->canBeApproved()) {
            return false;
        }

        if (config('payroll.enforce_segregation_of_duties', true)) {
            if ($user->id === (int) $payslip->created_by || $user->id === (int) $payslip->submitted_by) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can request changes for the payslip.
     */
    public function requestChanges(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('approvals.decide') && $payslip->canRequestChanges();
    }

    /**
     * Determine whether the user can finalize the payslip.
     */
    public function finalize(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.finalize') && $payslip->canBeFinalized();
    }

    /**
     * Determine whether the user can void a finalized payslip.
     */
    public function void(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.finalize') && $payslip->canBeVoided();
    }

    /**
     * Determine whether the user can preview the payslip PDF.
     */
    public function preview(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.view') && in_array($payslip->status, [
            PayslipStatus::APPROVED->value,
            PayslipStatus::FINALIZED->value,
        ], true);
    }

    /**
     * Determine whether the user can download the payslip PDF.
     * Strictly restricted to finalized payslips with immutable snapshots.
     */
    public function download(User $user, Payslip $payslip): bool
    {
        return $user->hasPermission('payslips.view') && $payslip->status === PayslipStatus::FINALIZED->value;
    }
}
