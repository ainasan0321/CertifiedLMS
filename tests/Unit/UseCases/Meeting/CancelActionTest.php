<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\User;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_meeting_refunds_quota_and_notifies_counterparty(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $beforeNotificationCount = Notification::count();

        $canceled = app(CancelAction::class)($meeting, $student);

        $this->assertSame(MeetingStatus::Canceled, $canceled->status);
        $this->assertSame($student->id, $canceled->canceled_by_user_id);
        $this->assertNotNull($canceled->canceled_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);

        $this->assertSame($beforeNotificationCount + 1, Notification::count());
    }

    public function test_throws_exception_when_meeting_is_not_reserved(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $this->expectException(MeetingStatusTransitionException::class);

        app(CancelAction::class)($meeting, $student);
    }

    public function test_throws_exception_when_meeting_already_started(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->expectException(MeetingAlreadyStartedException::class);

        app(CancelAction::class)($meeting, $student);
    }
}