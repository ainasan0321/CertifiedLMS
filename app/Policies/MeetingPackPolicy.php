<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;

class MeetingPackPolicy
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, MeetingPack $plan): bool
    {
        return true;
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, MeetingPack $plan): bool
    {
        return true;
    }

    public function delete($user, MeetingPack $plan): bool
    {
        return $plan->status !== MeetingPackStatus::Published;
    }

    public function publish($user, MeetingPack $plan): bool
    {
        // 下書き(Draft)からのみ公開可能
        return $plan->status === MeetingPackStatus::Draft;
    }

    public function archive($user, MeetingPack $plan): bool
    {
        // 公開中(Published)からのみアーカイブ可能
        return $plan->status === MeetingPackStatus::Published;
    }
}
