<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @group external-api
 */
class AiChatTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    private function createLearningStudent(): User
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
            'profile_setup_completed' => true,
        ]);

        $cert = Certification::factory()->create(['name' => '基本情報技術者試験']);
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Learning,
        ]);

        $student->update(['default_enrollment_id' => $enrollment->id]);

        return $student->fresh();
    }

    private function createSectionFor(Certification $certification): Section
    {
        $part = Part::factory()->create(['certification_id' => $certification->id]);
        $chapter = Chapter::factory()->create(['part_id' => $part->id]);

        return Section::factory()->create([
            'chapter_id' => $chapter->id,
            'title' => '二分探索木の基礎',
            'body' => '二分探索木は左の子が小さく右の子が大きいデータ構造です。',
        ]);
    }

    public function test_learning_student_can_access_ai_chat_and_widget_is_visible(): void
    {
        config(['ai-chat.enabled' => true]);
        $student = $this->createLearningStudent();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertOk()
            ->assertDontSee('data-ai-chat-widget'); // AI相談画面ではウィジェット非表示

        $this->actingAs($student)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('data-ai-chat-widget');
    }

    public function test_non_student_or_graduated_student_cannot_access_ai_chat(): void
    {
        config(['ai-chat.enabled' => true]);

        $coach = User::factory()->create([
            'role' => UserRole::Coach,
            'status' => UserStatus::InProgress,
            'profile_setup_completed' => true,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::InProgress,
            'profile_setup_completed' => true,
        ]);
        $graduated = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Graduated,
            'profile_setup_completed' => true,
        ]);

        foreach ([$coach, $admin, $graduated] as $user) {
            $this->actingAs($user)
                ->get(route('ai-chat.index'))
                ->assertForbidden();
        }
    }

    public function test_feature_switch_disabled_returns_404(): void
    {
        config(['ai-chat.enabled' => false]);
        $student = $this->createLearningStudent();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertNotFound();
    }

    public function test_same_section_reuses_existing_conversation(): void
    {
        config(['ai-chat.enabled' => true]);
        $student = $this->createLearningStudent();
        $section = $this->createSectionFor($student->defaultEnrollment->certification);

        $first = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ])
            ->assertCreated()
            ->json('conversation.id');

        $second = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ])
            ->assertOk()
            ->json('conversation.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_owner_can_send_message_and_gemini_context_and_metadata_are_saved(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.auto_title' => true,
        ]);

        Http::fake([
            '://googleapis.com*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => '計算量は O(log n) です。']]],
                    ]],
                    'usageMetadata' => [
                        'promptTokenCount' => 45,
                        'candidatesTokenCount' => 18,
                    ],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => '二分探索木の計算量']]],
                    ]],
                ], 200),
        ]);

        $student = $this->createLearningStudent();
        $section = $this->createSectionFor($student->defaultEnrollment->certification);
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'section_id' => $section->id,
            'title' => '初期タイトル',
            'last_message_at' => now(),
        ]);

        $res = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '計算量を教えてください',
            ])
            ->assertOk();

        // 受講生向け JSON に観測メタデータが露出しないこと
        $res->assertJsonMissingPath('assistant_message.model');
        $res->assertJsonMissingPath('assistant_message.input_tokens');
        $res->assertJsonMissingPath('assistant_message.output_tokens');
        $res->assertJsonMissingPath('assistant_message.response_time_ms');

        // DB には観測メタデータが保存されていること
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'input_tokens' => 45,
            'output_tokens' => 18,
        ]);

        // タイトルが自動更新されていること
        $this->assertSame('二分探索木の計算量', $conversation->fresh()->title);

        // Gemini リクエストに Section と資格のコンテキストが含まれていること
        Http::assertSent(function ($request) {
            $sys = (string) data_get($request->data(), 'systemInstruction.parts.0.text');

            return str_contains($sys, '二分探索木の基礎')
                && str_contains($sys, '基本情報技術者試験');
        });
    }

    public function test_user_question_remains_even_when_gemini_fails(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
        ]);

        Http::fake([
            '://googleapis.com*' => Http::response(['error' => 'Server Error'], 503),
        ]);

        $student = $this->createLearningStudent();
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'title' => '相談',
            'last_message_at' => now(),
        ]);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '失敗しても残る質問',
            ])
            ->assertStatus(502)
            ->assertJson(['upstream_status' => 503]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'content' => '失敗しても残る質問',
        ]);
    }

    public function test_daily_limit_returns_429_and_counts_failed_attempts(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.daily_limit' => 1,
        ]);

        Http::fake([
            '://googleapis.com*' => Http::response([], 500),
        ]);

        $student = $this->createLearningStudent();
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'title' => '上限テスト',
            'last_message_at' => now(),
        ]);

        // 1回目（失敗してもカウントされる）
        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '1回目',
            ])
            ->assertStatus(502);

        // 2回目は日次上限で 429
        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '2回目',
            ])
            ->assertStatus(429);
    }

    public function test_other_student_cannot_view_update_delete_or_post_to_conversation(): void
    {
        config(['ai-chat.enabled' => true]);

        $owner = $this->createLearningStudent();
        $other = $this->createLearningStudent();

        $conversation = AiChatConversation::create([
            'user_id' => $owner->id,
            'title' => 'オーナーの会話',
            'last_message_at' => now(),
        ]);

        $this->actingAs($other)->get(route('ai-chat.conversations.show', $conversation))->assertForbidden();
        $this->actingAs($other)->patch(route('ai-chat.conversations.update', $conversation), ['title' => '乗っ取り'])->assertForbidden();
        $this->actingAs($other)->delete(route('ai-chat.conversations.destroy', $conversation))->assertForbidden();
        $this->actingAs($other)->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => '横入り'])->assertForbidden();
    }

    public function test_owner_can_rename_and_delete_conversation(): void
    {
        config(['ai-chat.enabled' => true]);

        $student = $this->createLearningStudent();

        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'title' => '旧タイトル',
            'last_message_at' => now(),
        ]);

        $this->actingAs($student)
            ->patch(route('ai-chat.conversations.update', $conversation), ['title' => '新タイトル'])
            ->assertRedirect(route('ai-chat.conversations.show', $conversation));

        $this->assertSame('新タイトル', $conversation->fresh()->title);
        $this->actingAs($student)
            ->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertRedirect(route('ai-chat.index'));
        $this->assertSoftDeleted('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_gemini_empty_response_returns_502_and_records_error_message(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
        ]);

        Http::fake([
            '://googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '']]],
                ]],
            ], 200),
        ]);

        $student = $this->createLearningStudent();
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'title' => '空応答テスト',
            'last_message_at' => now(),
        ]);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '空応答になる質問',
            ])
            ->assertStatus(502);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
            'error_detail' => 'Empty candidate response',
        ]);
    }

    public function test_gemini_connection_exception_returns_502_and_records_error_message(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
        ]);

        Http::fake([
            '://googleapis.com*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
        ]);

        $student = $this->createLearningStudent();
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'title' => '通信エラーテスト',
            'last_message_at' => now(),
        ]);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => 'タイムアウトする質問',
            ])
            ->assertStatus(502)
            ->assertJson(['upstream_status' => 503]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
        ]);
    }

    public function test_gemini_retries_on_transient_server_error_and_succeeds(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.auto_title' => false,
            'ai-chat.gemini.retry_times' => 2,
            'ai-chat.gemini.retry_sleep_ms' => 1,
        ]);

        Http::fake([
            '://googleapis.com*' => Http::sequence()
                ->push(['error' => 'Temporary 503'], 503)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'リトライ後に成功した回答']]],
                    ]],
                ], 200),
        ]);

        $student = $this->createLearningStudent();
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'title' => 'リトライテスト',
            'last_message_at' => now(),
        ]);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '1回目失敗2回目成功の質問',
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'リトライ後に成功した回答');

        Http::assertSentCount(2);
    }

    public function test_gemini_sends_conversation_history_in_expected_prompt_structure(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.auto_title' => false,
        ]);

        Http::fake([
            '://googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '2回目の回答']]],
                ]],
            ], 200),
        ]);

        $student = $this->createLearningStudent();
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'title' => '履歴構造テスト',
            'last_message_at' => now(),
        ]);

        // 1件目（User）を作成し、2分前の時刻にする
        $this->travelTo(now()->subMinutes(2));
        $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => '最初の質問',
        ]);

        // 2件目（Assistant）を作成し、1分前の時刻にする
        $this->travelTo(now()->subMinute());
        $conversation->messages()->create([
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => '最初の回答',
        ]);

        // 現在時刻に戻す
        $this->travelBack();

        // 3件目（追加の質問）を送信
        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '追加の質問',
            ])
            ->assertOk();

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);

        $secondRequestData = $recorded->last()[0]->data();
        $contents = array_values($secondRequestData['contents'] ?? []);

        $this->assertSame([
            ['role' => 'user', 'parts' => [['text' => '最初の質問']]],
            ['role' => 'model', 'parts' => [['text' => '最初の回答']]],
            ['role' => 'user', 'parts' => [['text' => '追加の質問']]],
        ], $contents);

        $this->assertStringContainsString(
            'AI 講師',
            (string) data_get($secondRequestData, 'systemInstruction.parts.0.text')
        );
    }
}
