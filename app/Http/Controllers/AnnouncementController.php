<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\User;
use App\Models\Certification;
use App\Models\Notification;
use App\Notifications\BroadcastAnnouncement;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $announcements = Announcement::with(['createdBy', 'targetCertification', 'targetUser'])
            ->orderBy('dispatched_at', 'desc')
            ->paginate(20);

        return view('announcement.management.index', compact('announcements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): view
    {
        $allCertifications = Certification::orderBy('name')->get();

        $certifications = $allCertifications->unique(function ($item) {
            return mb_convert_kana($item->name, 'asKV', 'UTF-8');
        });

        $students = User::where('role', UserRole::Student)->where('status', UserStatus::InProgress)->orderby('name')->get();

        return view('announcement.management.create', compact('certifications', 'students'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        // バリデーションチェック
        $validated = $request->validated();

        // 受講中の受講生を取得
        $query = User::where('role', UserRole::Student)->where('status', UserStatus::InProgress);

        // 資格指定の場合
        if ($validated['target_type'] === AnnouncementTargetType::Certification->value) {
            $query->whereHas('enrollments', function ($q) use ($validated) {
                $q->where('certification_id', $validated['target_certification_id'])
                    ->where('status', 'learning');
            });
        }elseif ($validated['target_type'] === AnnouncementTargetType::User->value) {
            $query->where('id', $validated['target_user_id']);
        }

        $targets = $query->get();
        
        if($targets->isEmpty()) {
            return redirect()->back()->withInput()->withErrors([
                'target_type' => '該当する受講生が存在しません。'
            ]);
        }

        DB::transaction(function () use ($validated, $targets) {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $validated['target_type'],
                'certification_id' => $validated['target_certification_id'] ?? null,
                'user_id' => $validated['target_user_id'] ?? null,
                'dispatched_count' => $targets->count(),
                'dispatched_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($targets as $user) {
                Notification::create([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => [
                        'announcement_id' => $announcement->id,
                        'title' => $announcement->title,
                        'body' => $announcement->body,
                        'notification_type' => 'admin_announcement',
                    ],
                    'read_at' => null,
                ]);
            }
        });

        return redirect()->route('announcements.management.index')->with('success', 'お知らせの配信が完了しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Announcement $announcement): View
    {
        $announcement->load(['targetCertification', 'targetUser', 'createdBy']);

        return view('announcement.management.show', compact('announcement'));
    }

}
