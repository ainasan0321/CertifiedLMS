{{--
    通知メール本文（HTML / テキスト両方をレンダリングするマークダウンメール）。
    構成: 見出し → 通知本文 → 該当画面へのアクションボタン → 署名。
    画面ではなくメールクライアントで表示される（Mail のマークダウンコンポーネントを使用）。
--}}

@component('mail::message')
# {{ $notification->data['title'] ?? '新しい通知が届いています' }}

{{ $notification->data['body'] ?? '新しい通知が届きました。詳細はシステムよりご確認ください。' }}

@if($notification->link_url)
@component('mail::button', ['url' => url($notification->link_url)])
該当画面を開いて確認する
@endcomponent
@endif

※本メールはLMSの通知システムより自動送信されています。
心当たりのない場合は、お手数ですが管理者までご連絡ください。

Certify LMS 運営チーム
@endcomponent