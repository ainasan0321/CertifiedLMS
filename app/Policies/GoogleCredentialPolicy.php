<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GoogleCredential;
use App\Models\User;

class GoogleCredentialPolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GoogleCredential $credential): bool
    {
        return $user->role === UserRole::Coach && $credential->user_id === $user->id;
    }

    public function connect(User $user): bool
    {
        return $user->role === UserRole::Coach;
    }
}
