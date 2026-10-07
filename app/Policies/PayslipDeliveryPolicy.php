<?php

namespace App\Policies;

use App\Models\PayslipDelivery;
use App\Models\User;

class PayslipDeliveryPolicy
{
    /**
     * Determine whether the user can view any delivery records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('deliveries.view');
    }

    /**
     * Determine whether the user can view the delivery record.
     */
    public function view(User $user, PayslipDelivery $delivery): bool
    {
        return $user->hasPermission('deliveries.view');
    }

    /**
     * Determine whether the user can trigger new delivery dispatch.
     */
    public function send(User $user): bool
    {
        return $user->hasPermission('deliveries.send');
    }

    /**
     * Determine whether the user can retry a failed delivery.
     */
    public function retry(User $user, PayslipDelivery $delivery): bool
    {
        return $user->hasPermission('deliveries.retry') && $delivery->canRetry();
    }
}
