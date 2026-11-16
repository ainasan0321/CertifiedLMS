<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCalendarService
{
    public function buildAuthUrl(string $state): string
    {
        $client = $this->createBaseClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function connectFromCode(User $coach, string $code): void
    {
        $client = $this->createBaseClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth token exchange failed: '.($token['error_description'] ?? $token['error']));
        }

        $existing = $coach->googleCredential;
        $refreshToken = $token['refresh_token'] ?? $existing?->refresh_token;
        $expiresIn = isset($token['expires_in']) ? (int) $token['expires_in'] : 3600;

        GoogleCredential::updateOrCreate(
            ['user_id' => $coach->id],
            [
                'calendar_id' => 'primary',
                'access_token' => $token['access_token'],
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds($expiresIn),
                'connected_at' => now(),
            ]
        );
    }

    public function disconnect(User $coach): void
    {
        $credential = $coach->googleCredential;
        if (! $credential) {
            return;
        }

        try {
            $client = $this->createBaseClient();
            $client->revokeToken($credential->access_token);
        } catch (Throwable $e) {
            Log::warning('Google token revoke failed (ignored)', ['user_id' => $coach->id, 'error' => $e->getMessage()]);
        }

        $credential->delete();
    }

    /**
     * 指定日の予定時間帯一覧（['start' => Carbon, 'end' => Carbon]）を返す。失敗時は空配列。
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function getBusyPeriodsForDay(User $coach, Carbon $date): array
    {
        $credential = $coach->googleCredential;
        if (! $credential) {
            return [];
        }

        try {
            $service = $this->createCalendarService($credential);
            $timeMin = $date->copy()->startOfDay()->toRfc3339String();
            $timeMax = $date->copy()->endOfDay()->toRfc3339String();

            $events = $service->events->listEvents($credential->calendar_id, [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'singleEvents' => true,
            ]);

            $periods = [];
            foreach ($events->getItems() as $event) {
                if ($event->getTransparency() === 'transparent') {
                    continue;
                }
                $startRaw = $event->getStart()?->getDateTime() ?? $event->getStart()?->getDate();
                $endRaw = $event->getEnd()?->getDateTime() ?? $event->getEnd()?->getDate();
                if ($startRaw && $endRaw) {
                    $periods[] = [
                        'start' => Carbon::parse($startRaw),
                        'end' => Carbon::parse($endRaw),
                    ];
                }
            }

            return $periods;
        } catch (Throwable $e) {
            Log::warning('Google Calendar fetch events failed (fallback to available)', [
                'coach_id' => $coach->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 指定 60 分枠に Google 予定が重複しているか判定。失敗時は false（空き扱い）。
     */
    public function hasConflict(User $coach, Carbon $slotStart): bool
    {
        if (! $coach->googleCredential) {
            return false;
        }

        $slotEnd = $slotStart->copy()->addHour();
        $periods = $this->getBusyPeriodsForDay($coach, $slotStart);

        foreach ($periods as $period) {
            if ($period['start']->lt($slotEnd) && $period['end']->gt($slotStart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 面談予約をコーチの Google カレンダーへ登録し、イベント ID を返す。失敗時は null。
     */
    public function createMeetingEvent(Meeting $meeting): ?string
    {
        $coach = $meeting->coach;
        $credential = $coach?->googleCredential;
        if (! $credential) {
            return null;
        }

        try {
            $service = $this->createCalendarService($credential);
            $studentName = $meeting->student?->name ?? '受講生';

            $descriptionLines = ["受講生: {$studentName}"];
            if ($meeting->topic) {
                $descriptionLines[] = "相談内容: {$meeting->topic}";
            }
            if ($meeting->meeting_url_snapshot) {
                $descriptionLines[] = "面談URL: {$meeting->meeting_url_snapshot}";
            }

            $event = new Event([
                'summary' => "1on1面談（{$studentName}）",
                'location' => $meeting->meeting_url_snapshot ?? '',
                'description' => implode("\n", $descriptionLines),
                'start' => new EventDateTime(['dateTime' => $meeting->scheduled_at->toRfc3339String()]),
                'end' => new EventDateTime(['dateTime' => $meeting->scheduled_at->copy()->addHour()->toRfc3339String()]),
            ]);

            $created = $service->events->insert($credential->calendar_id, $event);

            return $created->getId();
        } catch (Throwable $e) {
            Log::warning('Google Calendar create event failed (ignored)', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Google カレンダーの予定を削除する。失敗時は無視。
     */
    public function deleteMeetingEvent(Meeting $meeting): void
    {
        if (! $meeting->google_calendar_event_id) {
            return;
        }

        $credential = $meeting->coach?->googleCredential;
        if (! $credential) {
            return;
        }

        try {
            $service = $this->createCalendarService($credential);
            $service->events->delete($credential->calendar_id, $meeting->google_calendar_event_id);
        } catch (Throwable $e) {
            Log::warning('Google Calendar delete event failed (ignored)', [
                'meeting_id' => $meeting->id,
                'event_id' => $meeting->google_calendar_event_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createBaseClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));
        $client->setScopes([Calendar::CALENDAR_EVENTS]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    protected function createCalendarService(GoogleCredential $credential): Calendar
    {
        $client = $this->createBaseClient();
        $client->setAccessToken([
            'access_token' => $credential->access_token,
            'refresh_token' => $credential->refresh_token,
            'expires_in' => $credential->expires_at ? max(0, now()->diffInSeconds($credential->expires_at, false)) : 0,
        ]);

        if ($client->isAccessTokenExpired() && $credential->refresh_token) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($credential->refresh_token);
            if (! isset($newToken['error']) && isset($newToken['access_token'])) {
                $credential->update([
                    'access_token' => $newToken['access_token'],
                    'refresh_token' => $newToken['refresh_token'] ?? $credential->refresh_token,
                    'expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
                ]);
            }
        }

        return new Calendar($client);
    }
}
