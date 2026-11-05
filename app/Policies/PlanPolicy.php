<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role == 'admin') {
            return false;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Plan $plan): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Plan $plan): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Plan $plan): bool
    {
        return $plan->users()->count() === 0;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function publish(User $user, Plan $plan): bool
    {
        return $plan->status->value === 'draft';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function archive(User $user, Plan $plan): bool
    {
        return $plan->status->value === 'published';
    }

    public function unarchive(User $user, Plan $plan): bool
    {
        return $plan->status->value === 'archived';
    }
}
