<?php

namespace App\Policies;

use App\Models\EnrollmentNote;
use App\Models\Enrollment;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;

class EnrollmentNotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::Coach;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EnrollmentNote $enrollmentNote): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        if ($user->role === UserRole::Admin){
            return true;
        }

        if($user->role === UserRole::Coach){
            return in_array($enrollment->certification_id, $user->coachingCertificationIds(), true);
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Coach) {
            return $note->author_id === $user->id && in_array($note->enrollment->certification_id, $user->coachingCertificationIds(), true);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->update($user, $note);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EnrollmentNote $enrollmentNote): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EnrollmentNote $enrollmentNote): bool
    {
        //
    }
}
