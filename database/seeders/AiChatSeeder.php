<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('email', 'student@certify-lms.test')->first()
            ?? User::where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->first();

        if (! $student) {
            return;
        }

        // 重複投入防止
        if ($student->aiChatConversations()->exists()) {
            return;
        }

        $section = Section::published()->first();

        // 1. 教材から始めた会話（正常応答・今日）
        $sectionConversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'section_id' => $section?->id,
            'title' => $section?->title ? "{$section->title}の要点整理" : '基礎理論の要点整理',
            'last_message_at' => now()->subMinutes(15),
        ]);

        $sectionConversation->messages()->createMany([
            [
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => 'このセクションで試験に出やすいポイントを3つ教えてください。',
                'created_at' => now()->subMinutes(16),
                'updated_at' => now()->subMinutes(16),
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => "試験対策として押さえるべき重要ポイントは以下の3点です。\n\n1. **基本定義と仕組み**：用語の意味と処理の流れを正確に区別できるようにしましょう。\n2. **計算量・メリットとデメリット**：他の手法と比較した際の特徴が頻出です。\n3. **具体例でのトレース**：小さな入力例で手順を追えるようにしておくと得点源になります。",
                'model' => config('ai-chat.gemini.model', 'gemini-2.5-flash'),
                'input_tokens' => 142,
                'output_tokens' => 168,
                'response_time_ms' => 1240,
                'created_at' => now()->subMinutes(15),
                'updated_at' => now()->subMinutes(15),
            ],
        ]);

        // 2. 教材によらない会話（AI 応答エラーを含む・再送確認用・3日前）
        $errorConversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'section_id' => null,
            'title' => '模擬試験の学習スケジュール相談',
            'last_message_at' => now()->subDays(3),
        ]);

        $errorConversation->messages()->createMany([
            [
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => '試験日まであと2ヶ月です。平日の勉強時間の配分をどう組めばよいですか？',
                'created_at' => now()->subDays(3)->subMinutes(2),
                'updated_at' => now()->subDays(3)->subMinutes(2),
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => "残り2ヶ月であれば、前半1ヶ月でインプットと単元別演習を一周し、後半1ヶ月は模擬試験と苦手分野の復習に充てるのがおすすめです。\n\n平日は1日60〜90分を目安に「新しい単元30分+前日の復習30分」に分けると定着しやすくなります。",
                'model' => config('ai-chat.gemini.model', 'gemini-2.5-flash'),
                'input_tokens' => 110,
                'output_tokens' => 135,
                'response_time_ms' => 980,
                'created_at' => now()->subDays(3)->subMinute(),
                'updated_at' => now()->subDays(3)->subMinute(),
            ],
            [
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => '苦手分野の復習はどのタイミングで挟むのが効果的ですか？',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Error,
                'content' => '',
                'error_detail' => 'HTTP 503: Service Unavailable',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
        ]);

        // 3. 教材によらない会話（過去履歴確認用・12日前）
        $oldConversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => null,
            'section_id' => null,
            'title' => '暗記カードと過去問の使い分け',
            'last_message_at' => now()->subDays(12),
        ]);

        $oldConversation->messages()->createMany([
            [
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => '用語の暗記が苦手です。効果的な覚え方はありますか？',
                'created_at' => now()->subDays(12)->subMinute(),
                'updated_at' => now()->subDays(12)->subMinute(),
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => '単語単体ではなく「何の課題を解決する仕組みか」とセットで覚えると忘れにくくなります。スキマ時間に確認し、週末に過去問でアウトプットするのがおすすめです。',
                'model' => config('ai-chat.gemini.model', 'gemini-2.5-flash'),
                'input_tokens' => 95,
                'output_tokens' => 112,
                'response_time_ms' => 890,
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(12),
            ],
        ]);
    }
}
