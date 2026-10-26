<?php

namespace App\Policies;

use App\Models\User;
use App\Models\QaThread;
use App\Models\QaReply;
use Illuminate\Auth\Access\Response;

class QaReplyPolicy
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
    public function view(User $user, QaReply $qaReply): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?QaThread $qaThread = null): bool
    {
        if(!$qaThread)
            {
                return false;
            }

        if ($user->role->value === 'student')
            {
                return true;
            }
        if ($user->role->value === 'coach')
            {
                return true;
            }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QaReply $qaReply): bool
    {
        return $user->id === $qaReply->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QaReply $qaReply): bool
    {
        if($user->role->value === 'admin') {
            return true;
        }
        
        return $user->id === $qaReply->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, QaReply $qaReply): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, QaReply $qaReply): bool
    {
        //
    }
}
