<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCredential;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\UseCases\Meeting\CancelAction;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * @group external-api
 */
class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_redirect_to_google_oauth_and_store_state(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('buildAuthUrl')
                ->once()
                ->with(Mockery::type('string'))
                ->andReturn('https://google.com');
        });

        $response = $this->actingAs($coach)
            ->get(route('settings.google-calendar.redirect', ['redirect_path' => '/settings/availability']));

        $response->assertRedirect('https://google.com');
        $this->assertNotEmpty(session('google_calendar_oauth_state'));
    }

    public function test_non_coach_cannot_access_google_calendar_routes(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach ([$student, $admin] as $user) {
            $this->actingAs($user)
                ->get(route('settings.google-calendar.redirect'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('settings.google-calendar.callback'))
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('settings.google-calendar.destroy'))
                ->assertForbidden();
        }
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);

        $this->actingAs($coach)
            ->withSession([
                'google_calendar_oauth_state' => 'valid-state',
                'google_calendar_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'invalid-state',
                'code' => 'auth-code',
            ]))
            ->assertRedirect('/settings/availability')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_callback_succeeds_when_state_matches(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('connectFromCode')
                ->once()
                ->with(Mockery::any(), 'valid-code')
                ->andReturnUsing(function (User $u) {
                    GoogleCredential::create([
                        'user_id' => $u->id,
                        'calendar_id' => 'primary',
                        'access_token' => 'access-token',
                        'refresh_token' => 'refresh-token',
                        'expires_at' => now()->addHour(),
                        'connected_at' => now(),
                    ]);
                });
        });

        $this->actingAs($coach)
            ->withSession([
                'google_calendar_oauth_state' => 'valid-state',
                'google_calendar_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'valid-state',
                'code' => 'valid-code',
            ]))
            ->assertRedirect('/settings/availability')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
        ]);
    }

    public function test_coach_can_disconnect_google_calendar(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);
        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('disconnect')
                ->once()
                ->with(Mockery::type(User::class))
                ->andReturnUsing(fn (User $u) => $u->googleCredential?->delete());
        });

        $this->actingAs($coach)
            ->from('/settings/availability')
            ->delete(route('settings.google-calendar.destroy'))
            ->assertRedirect('/settings/availability')
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('google_credentials', [
            'user_id' => $coach->id,
        ]);
    }

    public function test_google_busy_slots_are_excluded_from_availability_for_connected_coach(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $certification = Certification::factory()->create();
        $coachConnected = User::factory()->create(['role' => UserRole::Coach]);
        $coachUnconnected = User::factory()->create(['role' => UserRole::Coach]);

        $certification->coaches()->attach([
            $coachConnected->id => ['assigned_by_user_id' => $admin->id, 'assigned_at' => now()],
            $coachUnconnected->id => ['assigned_by_user_id' => $admin->id, 'assigned_at' => now()],
        ]);

        GoogleCredential::create([
            'user_id' => $coachConnected->id,
            'calendar_id' => 'primary',
            'access_token' => 'token',
            'connected_at' => now(),
        ]);

        $targetDate = Carbon::parse('2026-12-01');
        CoachAvailability::create([
            'coach_id' => $coachConnected->id,
            'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'is_active' => true,
        ]);
        CoachAvailability::create([
            'coach_id' => $coachUnconnected->id,
            'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'is_active' => true,
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock) use ($coachConnected, $targetDate) {
            $mock->shouldReceive('getBusyPeriodsForDay')
                ->once()
                ->with(Mockery::on(fn (User $u) => $u->id === $coachConnected->id), Mockery::any())
                ->andReturn([
                    [
                        'start' => $targetDate->copy()->setTime(10, 0),
                        'end' => $targetDate->copy()->setTime(11, 0),
                    ],
                ]);
        });

        $service = app(MeetingAvailabilityService::class);
        $slots = $service->slotsForCertification($certification, $targetDate)->keyBy(fn ($s) => $s['slot_start']->format('H:i'));

        $this->assertSame(1, $slots['10:00']['available_coach_count']);
        $this->assertSame(2, $slots['11:00']['available_coach_count']);
    }

    public function test_meeting_store_and_cancel_sync_with_google_calendar_and_fallback_on_failure(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $certification = Certification::factory()->create();
        $coach = User::factory()->create(['role' => UserRole::Coach, 'meeting_url' => 'https://example.com']);
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'max_meetings' => 5,
        ]);

        $certification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'token',
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        MeetingQuotaTransaction::factory()->create([
            'user_id' => $student->id,
            'amount' => 3,
        ]);

        $scheduledAt = now()->addDays(3)->setTime(14, 0, 0);
        CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => $scheduledAt->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '16:00:00',
            'is_active' => true,
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('getBusyPeriodsForDay')->andReturn([]);
            $mock->shouldReceive('hasConflict')->andReturn(false);
            $mock->shouldReceive('createMeetingEvent')->once()->andReturn('gcal-event-123');
            $mock->shouldReceive('deleteMeetingEvent')->once();
        });

        $meeting = app(StoreAction::class)($enrollment, $scheduledAt, '学習計画の相談');
        $this->assertSame('gcal-event-123', $meeting->google_calendar_event_id);

        app(CancelAction::class)($meeting, $student);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => 'canceled',
        ]);
    }

    public function test_meeting_flow_continues_even_when_google_calendar_api_fails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $certification = Certification::factory()->create();
        $coach = User::factory()->create(['role' => UserRole::Coach]);
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'max_meetings' => 5,
        ]);

        $certification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'token',
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        MeetingQuotaTransaction::factory()->create([
            'user_id' => $student->id,
            'amount' => 3,
        ]);

        $scheduledAt = now()->addDays(4)->setTime(15, 0, 0);
        CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => $scheduledAt->dayOfWeek,
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('getBusyPeriodsForDay')->andReturn([]);
            $mock->shouldReceive('hasConflict')->andReturn(false);
            $mock->shouldReceive('createMeetingEvent')->once()->andReturn(null);
            $mock->shouldReceive('deleteMeetingEvent')->once();
        });

        $meeting = app(StoreAction::class)($enrollment, $scheduledAt, 'フォールバック確認');
        $this->assertNull($meeting->google_calendar_event_id);

        app(CancelAction::class)($meeting, $student);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => 'canceled',
        ]);
    }
}
