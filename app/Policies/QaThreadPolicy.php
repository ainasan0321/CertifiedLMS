<?php

namespace App\Policies;

use App\Models\QaThread;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;

class QaThreadPolicy
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
    public function view(User $user, QaThread $qaThread): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role->value === 'student' || $user->role === UserRole::Student;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QaThread $qaThread): bool
    {
        return $user->id === $qaThread->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QaThread $qaThread): bool
    {
        if($user->role->value === 'admin') {
            return true;
        }
        
        return $user->id === $qaThread->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function resolve(User $user, QaThread $qaThread): bool
    {
        return $user->id === $qaThread->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function unresolve(User $user, QaThread $qaThread): bool
    {
        return $user->id === $qaThread->user_id;
    }
}
