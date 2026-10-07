<?php

namespace App\Policies;

use App\Models\HolidayCalendar;
use App\Models\User;

class HolidayCalendarPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payroll.holiday.view')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HolidayCalendar $holiday): bool
    {
        return $user->hasPermission('payroll.holiday.view')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('payroll.holiday.update')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HolidayCalendar $holiday): bool
    {
        return $user->hasPermission('payroll.holiday.update')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HolidayCalendar $holiday): bool
    {
        return $user->hasPermission('payroll.holiday.update')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can sync holidays.
     */
    public function sync(User $user): bool
    {
        return $user->hasPermission('payroll.holiday.sync')
            || $user->hasRole('super_admin');
    }
}
