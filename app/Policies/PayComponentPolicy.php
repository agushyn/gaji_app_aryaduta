<?php

namespace App\Policies;

use App\Models\PayComponent;
use App\Models\User;

class PayComponentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pay-components.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PayComponent $payComponent): bool
    {
        return $user->hasPermission('pay-components.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('pay-components.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PayComponent $payComponent): bool
    {
        return $user->hasPermission('pay-components.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PayComponent $payComponent): bool
    {
        return $user->hasPermission('pay-components.manage');
    }
}
