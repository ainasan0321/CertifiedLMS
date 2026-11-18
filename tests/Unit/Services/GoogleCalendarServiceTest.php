<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\Events;
use Google\Service\Calendar\Resource\Events as EventsResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * @group external-api
 */
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTestableService(
        ?GoogleClient $mockClient = null,
        ?Calendar $mockCalendar = null,
    ): GoogleCalendarService {
        return new class($mockClient, $mockCalendar) extends GoogleCalendarService
        {
            public function __construct(
                private readonly ?GoogleClient $mockClient,
                private readonly ?Calendar $mockCalendar,
            ) {}

            protected function createBaseClient(): GoogleClient
            {
                return $this->mockClient ?? parent::createBaseClient();
            }

            protected function createCalendarService(GoogleCredential $credential): Calendar
            {
                if ($this->mockCalendar !== null) {
                    // トークンリフレッシュ処理の検証を通すため、モックClientがある場合は親のトークン更新ロジックだけ実行
                    if ($this->mockClient !== null) {
                        parent::createCalendarService($credential);
                    }

                    return $this->mockCalendar;
                }

                return parent::createCalendarService($credential);
            }
        };
    }

    public function test_build_auth_url_sets_state_and_returns_url(): void
    {
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setState')->once()->with('random-state-123');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://google.com');

        $service = $this->makeTestableService(mockClient: $client);

        $url = $service->buildAuthUrl('random-state-123');

        $this->assertSame('https://google.com', $url);
    }

    public function test_connect_from_code_exchanges_token_and_saves_credential(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('auth-code-xyz')
            ->andReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]);

        $service = $this->makeTestableService(mockClient: $client);
        $service->connectFromCode($coach, 'auth-code-xyz');

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
        ]);
    }

    public function test_connect_from_code_throws_exception_when_token_exchange_fails(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('invalid-code')
            ->andReturn([
                'error' => 'invalid_grant',
                'error_description' => 'Malformed auth code.',
            ]);

        $service = $this->makeTestableService(mockClient: $client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Malformed auth code.');

        $service->connectFromCode($coach, 'invalid-code');
    }

    public function test_disconnect_revokes_token_and_deletes_credential_even_if_revoke_fails(): void
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

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('revokeToken')
            ->once()
            ->with('access-token')
            ->andThrow(new \RuntimeException('Network error during revoke'));

        $service = $this->makeTestableService(mockClient: $client);
        $service->disconnect($coach);

        $this->assertDatabaseMissing('google_credentials', [
            'user_id' => $coach->id,
        ]);
    }

    public function test_get_busy_periods_for_day_returns_opaque_events_and_ignores_transparent_events(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);
        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'valid-token',
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $opaqueEvent = new Event([
            'transparency' => 'opaque',
            'start' => new EventDateTime(['dateTime' => '2026-12-01T10:00:00+09:00']),
            'end' => new EventDateTime(['dateTime' => '2026-12-01T11:30:00+09:00']),
        ]);
        $transparentEvent = new Event([
            'transparency' => 'transparent',
            'start' => new EventDateTime(['dateTime' => '2026-12-01T13:00:00+09:00']),
            'end' => new EventDateTime(['dateTime' => '2026-12-01T14:00:00+09:00']),
        ]);

        $eventsList = Mockery::mock(Events::class);
        $eventsList->shouldReceive('getItems')->once()->andReturn([$opaqueEvent, $transparentEvent]);

        $eventsResource = Mockery::mock(EventsResource::class);
        $eventsResource->shouldReceive('listEvents')
            ->once()
            ->with('primary', Mockery::on(fn (array $params) => $params['singleEvents'] === true))
            ->andReturn($eventsList);

        $calendar = Mockery::mock(Calendar::class);
        $calendar->events = $eventsResource;

        $service = $this->makeTestableService(mockCalendar: $calendar);
        $periods = $service->getBusyPeriodsForDay($coach, Carbon::parse('2026-12-01'));

        $this->assertCount(1, $periods);
        $this->assertSame('10:00', $periods[0]['start']->format('H:i'));
        $this->assertSame('11:30', $periods[0]['end']->format('H:i'));
    }

    public function test_expired_token_refreshes_automatically_and_retries_calendar_operation(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);
        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'valid-refresh-token',
            'expires_at' => now()->subMinutes(10),
            'connected_at' => now()->subDay(),
        ]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setAccessToken')->once();
        $client->shouldReceive('isAccessTokenExpired')->once()->andReturn(true);
        $client->shouldReceive('fetchAccessTokenWithRefreshToken')
            ->once()
            ->with('valid-refresh-token')
            ->andReturn([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
            ]);

        $eventsList = Mockery::mock(Events::class);
        $eventsList->shouldReceive('getItems')->once()->andReturn([]);

        $eventsResource = Mockery::mock(EventsResource::class);
        $eventsResource->shouldReceive('listEvents')->once()->andReturn($eventsList);

        $calendar = Mockery::mock(Calendar::class);
        $calendar->events = $eventsResource;

        $service = $this->makeTestableService(mockClient: $client, mockCalendar: $calendar);
        $periods = $service->getBusyPeriodsForDay($coach, Carbon::parse('2026-12-01'));

        $this->assertSame([], $periods);
        $this->assertSame('refreshed-access-token', $credential->fresh()->access_token);
    }

    public function test_refresh_failure_falls_back_gracefully_without_throwing(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);
        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'revoked-refresh-token',
            'expires_at' => now()->subMinutes(10),
            'connected_at' => now()->subDay(),
        ]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setAccessToken')->once();
        $client->shouldReceive('isAccessTokenExpired')->once()->andReturn(true);
        $client->shouldReceive('fetchAccessTokenWithRefreshToken')
            ->once()
            ->with('revoked-refresh-token')
            ->andThrow(new \RuntimeException('Refresh token revoked'));

        $calendar = Mockery::mock(Calendar::class);

        $service = $this->makeTestableService(mockClient: $client, mockCalendar: $calendar);
        $periods = $service->getBusyPeriodsForDay($coach, Carbon::parse('2026-12-01'));

        // 例外を握りつぶし、空き扱い（空配列）にフォールバックすること
        $this->assertSame([], $periods);
    }

    public function test_create_and_delete_meeting_event_and_handle_already_deleted_event(): void
    {
        $coach = User::factory()->create(['role' => UserRole::Coach]);
        $student = User::factory()->create(['role' => UserRole::Student, 'name' => 'テスト受講生']);

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'valid-token',
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $meeting = Meeting::factory()->create([
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'scheduled_at' => Carbon::parse('2026-12-05 15:00:00'),
            'topic' => '進捗相談',
            'meeting_url_snapshot' => 'example.com',
        ]);

        $createdEvent = Mockery::mock(Event::class);

        $createdEvent->shouldReceive('getId')->once()->andReturn('created-event-999');

        $eventsResource = Mockery::mock(EventsResource::class);

        $eventsResource->shouldReceive('insert')
            ->once()
            ->with('primary', Mockery::type(Event::class))
            ->andReturn($createdEvent);

        // 削除済み予定（404 / 410 Not Found 等）でも例外を外に投げず正常終了すること
        $eventsResource->shouldReceive('delete')
            ->once()
            ->with('primary', 'created-event-999')
            ->andThrow(new \RuntimeException('Not Found (404)'));

        $calendar = Mockery::mock(Calendar::class);

        $calendar->events = $eventsResource;

        $service = $this->makeTestableService(mockCalendar: $calendar);

        $eventId = $service->createMeetingEvent($meeting);

        $this->assertSame('created-event-999', $eventId);

        $meeting->update(['google_calendar_event_id' => $eventId]);

        $service->deleteMeetingEvent($meeting);

        $this->assertTrue(true);
    }
}
