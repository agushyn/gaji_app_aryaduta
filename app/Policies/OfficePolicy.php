<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;

class OfficePolicy
{
    /**
     * Determine whether the user can view any offices.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('offices.view');
    }

    /**
     * Determine whether the user can view the office.
     */
    public function view(User $user, Office $office): bool
    {
        return $user->hasPermission('offices.view');
    }

    /**
     * Determine whether the user can create offices.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('offices.manage');
    }

    /**
     * Determine whether the user can update the office.
     */
    public function update(User $user, Office $office): bool
    {
        return $user->hasPermission('offices.manage');
    }

    /**
     * Determine whether the user can delete the office.
     */
    public function delete(User $user, Office $office): bool
    {
        return $user->hasPermission('offices.manage');
    }
}
