<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class NotificationPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $notification): bool
    {
        return (string) $notification->notifiable_id === (string) $user->id;
    }
}
