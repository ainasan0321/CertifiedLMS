<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $tab = $request->query('tab', 'all');

        $query = Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->latest();

        if ($tab === 'unread') {
            $query->unread();
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCount = Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->unread()
            ->count();

        return view('notifications.index', compact('notifications', 'unreadCount', 'tab'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function read(Notification $notification): RedirectResponse
    {
        // 認可チェック
        $this->authorize('update', $notification);

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        return redirect($notification->link_url ?? route('notifications.index'));
    }

    public function markAllAsRead()
    {
        Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', 'すべての通知を既読にしました。');
    }
}
