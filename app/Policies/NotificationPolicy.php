<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $notification): bool
    {
        return (string)$notification->notifiable_id === (string)$user->id;
    }


}
