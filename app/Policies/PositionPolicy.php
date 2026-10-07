<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    /**
     * Determine whether the user can view any positions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('positions.view');
    }

    /**
     * Determine whether the user can view the position.
     */
    public function view(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.view');
    }

    /**
     * Determine whether the user can create positions.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('positions.manage');
    }

    /**
     * Determine whether the user can update the position.
     */
    public function update(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.manage');
    }

    /**
     * Determine whether the user can delete the position.
     */
    public function delete(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.manage');
    }
}
