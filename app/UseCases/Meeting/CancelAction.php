<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;

final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(Meeting $meeting, User $actor): Meeting
    {
        $canceled = DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, $locked->id);

            $targetUser = $actor->id === $locked->student_id
                ? ($locked->coach ?? User::find($locked->coach_id))
                : ($locked->student ?? User::find($locked->student_id));

            if ($targetUser) {
                $formattedDate = $locked->scheduled_at->format('Y-m-d H:i');
                Notification::sendNotification(
                    $targetUser,
                    '面談がキャンセルされました',
                    'meeting_canceled',
                    "{$actor->name}様により、{$formattedDate} の面談予約がキャンセルされました。",
                    '/meetings'
                );
            }

            return $locked->fresh();
        });

        // トランザクション確定後に Google カレンダーの予定を削除（失敗時は無視して継続）
        $this->googleCalendarService->deleteMeetingEvent(
            $canceled->loadMissing('coach.googleCredential')
        );

        return $canceled;
    }
}
