<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class AiChatService
{
    public function __construct(
        private readonly GeminiClient $gemini,
    ) {}

    /**
     * @return array{user_message: AiChatMessage, assistant_message: AiChatMessage}
     */
    public function sendMessage(User $user, AiChatConversation $conversation, string $content): array
    {
        $this->ensureWithinDailyLimit($user);

        // 1. API 呼び出し前にユーザー質問を保存（失敗時も履歴に残す）
        $userMessage = $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => $content,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // 2. 直近履歴 + 今回の質問を組み立て
        $contents = $this->buildContents($conversation, $userMessage);
        $systemInstruction = $this->buildSystemInstruction($conversation);

        try {
            $result = $this->gemini->generateContent($contents, $systemInstruction);
        } catch (GeminiException $e) {
            $conversation->messages()->create([
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Error,
                'content' => '',
                'error_detail' => Str::limit($e->getMessage(), 1000),
            ]);

            throw $e;
        }

        // 3. AI 応答 + 内部観測メタデータを保存
        $assistantMessage = $conversation->messages()->create([
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => $result['text'],
            'model' => $result['model'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'response_time_ms' => $result['response_time_ms'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        // 4. 初回完了時かつ自動タイトル有効なら見出しを更新
        $this->maybeGenerateTitle($conversation, $content, $result['text']);

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    private function ensureWithinDailyLimit(User $user): void
    {
        $limit = (int) config('ai-chat.daily_limit', 30);
        $todayCount = AiChatMessage::whereHas(
            'conversation',
            fn ($q) => $q->where('user_id', $user->id)
        )
            ->where('role', AiChatMessageRole::User)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayCount >= $limit) {
            throw new HttpResponseException(
                response()->json(['message' => 'Daily limit exceeded.'], 429)
            );
        }
    }

    /**
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function buildContents(AiChatConversation $conversation, AiChatMessage $latestUserMessage): array
    {
        $limit = (int) config('ai-chat.history_limit', 10);

        $history = $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed)
            ->where('id', '!=', $latestUserMessage->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse();

        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg->role === AiChatMessageRole::User ? 'user' : 'model',
                'parts' => [['text' => $msg->content]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $latestUserMessage->content]],
        ];

        return $contents;
    }

    private function buildSystemInstruction(AiChatConversation $conversation): string
    {
        $conversation->loadMissing(['section.chapter.part.certification', 'enrollment.certification']);

        $lines = ['あなたは資格学習を支援する AI 講師です。受講生の質問に対して、正確かつ分かりやすく日本語で回答してください。'];

        if ($conversation->section) {
            $section = $conversation->section;
            $lines[] = "【閲覧中の教材セクション】{$section->title}";
            if ($section->body) {
                $lines[] = "【教材本文抜粋】\n".Str::limit((string) $section->body, 3000);
            }
        }

        $certName = $conversation->enrollment?->certification?->name
            ?? $conversation->section?->chapter?->part?->certification?->name;

        if ($certName) {
            $lines[] = "【受講中の資格】{$certName}";
        }

        return implode("\n\n", $lines);
    }

    private function maybeGenerateTitle(AiChatConversation $conversation, string $userText, string $assistantText): void
    {
        if (! config('ai-chat.auto_title', true)) {
            return;
        }

        $completedCount = $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed)
            ->count();

        if ($completedCount !== 2) {
            return;
        }

        try {
            $prompt = "以下の質問と回答の内容を表す短い会話タイトルを日本語で1つだけ出力してください（25文字以内・記号やカギ括弧なし）。\n\n質問: {$userText}\n回答: ".Str::limit($assistantText, 300);
            $res = $this->gemini->generateContent([
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ]);
            $title = trim(str_replace(["\r", "\n", '「', '」'], '', $res['text']));
            if ($title !== '') {
                $conversation->update(['title' => Str::limit($title, 100, '')]);
            }
        } catch (\Throwable) {
            // タイトル生成失敗時は初期タイトルを維持
        }
    }
}
