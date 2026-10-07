<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;

class DivisionPolicy
{
    /**
     * Determine whether the user can view any divisions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('divisions.view');
    }

    /**
     * Determine whether the user can view the division.
     */
    public function view(User $user, Division $division): bool
    {
        return $user->hasPermission('divisions.view');
    }

    /**
     * Determine whether the user can create divisions.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('divisions.manage');
    }

    /**
     * Determine whether the user can update the division.
     */
    public function update(User $user, Division $division): bool
    {
        return $user->hasPermission('divisions.manage');
    }

    /**
     * Determine whether the user can delete the division.
     */
    public function delete(User $user, Division $division): bool
    {
        return $user->hasPermission('divisions.manage');
    }
}
