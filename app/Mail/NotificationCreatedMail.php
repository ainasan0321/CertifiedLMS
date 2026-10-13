<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    // 通知モデルを受け取る
    // メール本文のテンプレートに渡したいデータ（ユーザー情報や注文内容など）を、外部から受け取ってクラス内に保持するために使います。
    public function __construct(public Notification $notification) {}

    /**
     * Get the message envelope.
     */
    // メールの設定（宛先や件名）
    // メールの「封筒」にあたる部分です。メールの件名（Subject）や、送信元（From）のアドレスなどをここで設定します。
    public function envelope(): Envelope
    {
        // 通知データの中からタイトルを取得してメールの件名にする
        $title = $this->notification->data['title'] ?? '新しい通知が届いています';

        // これから届くメールの件名を設定
        return new Envelope(
            subject: '[Certify LMS] <通知タイトル>' . $title,
        );
    }

    /**
     * Get the message content definition.
     */
    // • メールの「中身」にあたる部分です。メール本文のデザインを記述するBladeビュー（テンプレート）の指定や、テキスト版メールの設定をここで記述します。

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.notification_created',
            with: [
                'notification' => $this->notification,
            ],
        );
    }
}
