<?php

namespace App\Policies;

use App\Models\EnrollmentGoal;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EnrollmentGoalPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EnrollmentGoal $enrollmentGoal): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, $enrollment = null): bool
    {
        if ($enrollment instanceof Enrollment) {
            return $user->id === $enrollment->user_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id;
    }
}
