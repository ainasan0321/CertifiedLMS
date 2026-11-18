<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiChatConversation;
use App\Models\User;

class AiChatConversationPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }
}
