<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AnnouncementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $auth, Announcement $announcement): bool
    {
        return $auth->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
