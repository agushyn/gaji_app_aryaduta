<?php

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollPeriodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll-periods.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('payroll-periods.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('payroll-periods.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('payroll-periods.manage') && $payrollPeriod->canBeModified();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('payroll-periods.manage') && $payrollPeriod->isDraft();
    }

    /**
     * Determine whether the user can submit the period for review.
     */
    public function submit(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('payroll-periods.manage') && $payrollPeriod->canBeSubmitted();
    }

    /**
     * Determine whether the user can approve the period.
     */
    public function approve(User $user, PayrollPeriod $payrollPeriod): bool
    {
        if (! $user->hasPermission('approvals.decide') || ! $payrollPeriod->canBeApproved()) {
            return false;
        }

        if (config('payroll.enforce_segregation_of_duties', true)) {
            if ($user->id === (int) $payrollPeriod->created_by || $user->id === (int) $payrollPeriod->submitted_by) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can request changes for the period.
     */
    public function requestChanges(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('approvals.decide') && $payrollPeriod->canRequestChanges();
    }

    /**
     * Determine whether the user can finalize the period.
     */
    public function finalize(User $user, PayrollPeriod $payrollPeriod): bool
    {
        return $user->hasPermission('payslips.finalize') && $payrollPeriod->canBeFinalized();
    }
}
