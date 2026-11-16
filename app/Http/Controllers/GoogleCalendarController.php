<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GoogleCredential;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleCalendarController extends Controller
{
    private const SESSION_STATE_KEY = 'google_calendar_oauth_state';

    private const SESSION_REDIRECT_KEY = 'google_calendar_redirect_path';

    public function __construct(
        private readonly GoogleCalendarService $calendarService,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $this->authorize('connect', GoogleCredential::class);

        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE_KEY, $state);

        $redirectPath = (string) $request->query('redirect_path', '/settings/availability');
        if (! str_starts_with($redirectPath, '/') || str_starts_with($redirectPath, '//')) {
            $redirectPath = '/settings/availability';
        }
        $request->session()->put(self::SESSION_REDIRECT_KEY, $redirectPath);

        return redirect()->away($this->calendarService->buildAuthUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorize('connect', GoogleCredential::class);

        $expectedState = (string) $request->session()->get(self::SESSION_STATE_KEY, '');
        $redirectPath = (string) $request->session()->get(self::SESSION_REDIRECT_KEY, '/settings/availability');
        $request->session()->forget([self::SESSION_STATE_KEY, self::SESSION_REDIRECT_KEY]);
        $actualState = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $actualState)) {
            return redirect($redirectPath)->with('error', '不正な連携リクエストです。もう一度お試しください。');
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect($redirectPath)->with('error', 'Googleアカウントとの連携がキャンセルされました。');
        }

        try {
            $this->calendarService->connectFromCode($request->user(), $code);
        } catch (Throwable) {
            return redirect($redirectPath)->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        return redirect($redirectPath)->with('status', 'Googleカレンダーと連携しました。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $credential = $user->googleCredential;

        if ($credential !== null) {
            $this->authorize('delete', $credential);
            $this->calendarService->disconnect($user);
        } else {
            $this->authorize('connect', GoogleCredential::class);
        }

        return back()->with('status', 'Googleカレンダーの連携を解除しました。');
    }
}
