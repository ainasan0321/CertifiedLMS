<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $baseQuery = Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id);

        $unreadCount = (clone $baseQuery)->unread()->count();

        $notifications = (clone $baseQuery)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'お知らせ',
                'body' => $n->data['body'] ?? '',
                'is_unread' => is_null($n->read_at),
                'created_at_human' => $n->created_at?->diffForHumans() ?? '',
                'redirect_url' => $this->resolveRedirectUrl($n),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        $unreadCount = Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'redirect_url' => $this->resolveRedirectUrl($notification),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'unread_count' => 0,
        ]);
    }

    private function resolveRedirectUrl(Notification $notification): string
    {
        if (($notification->data['notification_type'] ?? null) === 'admin_announcement') {
            return route('notifications.show', $notification);
        }

        return $notification->link_url ?? route('notifications.index');
    }
}
