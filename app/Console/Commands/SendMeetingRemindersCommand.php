<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Notifications\MeetingReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendMeetingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-meeting-reminders {--window=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '予約済み面談の事前リマインダー通知を送信します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $window = $this->option('window');

        if (! in_array($window, ['eve', 'one_hour_before'])) {
            $this->error('Invalid window option.');

            return Command::FAILURE;
        }

        $now = Carbon::now();

        // 対象となる面談の判定条件
        if ($window === 'eve') {
            // 前日判定: 明日の時間帯に含まれる面談
            $start = $now->copy()->addDay()->startOfDay();
            $end = $now->copy()->addDay()->endOfDay();
        } else {
            // 1時間前判定: 現時刻から55分〜65分後に開始する面談
            $start = $now->copy()->addMinutes(55);
            $end = $now->copy()->addMinutes(65);
        }

        // 予約済み（StatusがRESERVEDなど、キャンセル・完了以外）の面談を抽出
        $meetings = Meeting::where('status', MeetingStatus::Reserved->value)
            ->whereBetween('scheduled_at', [$start, $end])
            ->whereDoesntHave('reminders', function ($query) use ($window) {
                $query->where('window', $window);
            })
            ->with(['student', 'coach']) // 必要なリレーション
            ->get();

        $activeStatuses = [UserStatus::InProgress, UserStatus::Graduated];

        foreach ($meetings as $meeting) {
            // 2重起動時の競合を防ぐトランザクション処理
            DB::transaction(function () use ($meeting, $window, $activeStatuses) {
                // ロックをかけて、再度送信済みでないかチェック（Pessimistic Lock）
                $exists = MeetingReminder::where('meeting_id', $meeting->id)
                    ->where('window', $window)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return;
                }

                MeetingReminder::create([
                    'meeting_id' => $meeting->id,
                    'window' => $window,
                ]);

                $users = array_filter([$meeting->student, $meeting->coach]);

                foreach ($users as $user) {
                    if (in_array($user->status, $activeStatuses, true)) {
                        // 既存の通知基盤（アプリ内通知＋メール）を呼び出し
                        $user->notify(new MeetingReminderNotification($meeting, $window));
                    }
                }
            });
        }

        $this->info("Reminders sent for window: {$window}");

        return Command::SUCCESS;
    }
}
