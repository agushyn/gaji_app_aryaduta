<?php

namespace App\Policies;

use App\Models\PayrollCalculationConfig;
use App\Models\User;

class PayrollCalculationConfigPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.configuration.view')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PayrollCalculationConfig $config): bool
    {
        return $user->hasPermission('payroll.configuration.view')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('payroll.configuration.create')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ?PayrollCalculationConfig $config = null): bool
    {
        return $user->hasPermission('payroll.configuration.update')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PayrollCalculationConfig $config): bool
    {
        return $user->hasPermission('payroll.configuration.delete')
            || $user->hasRole('super_admin');
    }
}
