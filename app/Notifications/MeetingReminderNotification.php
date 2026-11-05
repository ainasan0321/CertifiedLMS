<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification
{
    use Queueable;

    public $meeting;

    public $window;

    /**
     * Create a new notification instance.
     */
    public function __construct(Meeting $meeting, string $window)
    {
        $this->meeting = $meeting;
        $this->window = $window;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', MeetingDatabaseChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $timingLabel = $this->window === 'eve' ? '明日' : 'まもなく（1時間前）';
        $formattedTime = $this->meeting->scheduled_at->format('Y/m/d H:i');

        return (new MailMessage)
            ->subject("【リマインダー】面談が{$timingLabel}開催されます")
            ->greeting($notifiable->name.' 様')
            ->line('ご予約いただいている1on1面談の時間が近づいてまいりました。')
            ->line("日時: {$formattedTime}")
            ->action('面談詳細を確認する', url('/meetings/'.$this->meeting->id));
    }
}
