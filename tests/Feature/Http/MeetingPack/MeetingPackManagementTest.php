<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\MeetingPack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPackManagementTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        // 共通のテストユーザーを用意
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->student = User::factory()->create(['role' => UserRole::Student->value]);
    }

    public function test_only_admin_can_access_index_and_student_is_denied(): void
    {
        // 管理者はアクセス成功 (200)
        $this->actingAs($this->admin)
            ->get(route('admin.meeting-packs.index'))
            ->assertStatus(200);

        // 受講生はアクセス拒否 (403)
        $this->actingAs($this->student)
            ->get(route('admin.meeting-packs.index'))
            ->assertStatus(403);
    }

    public function test_store_validation_fails_when_name_is_empty(): void
    {
        $invalidData = [
            'name' => '', // 必須エラー
            'meeting_count' => 5,
            'price' => 5000,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.store'), $invalidData);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_only_draft_pack_can_be_published(): void
    {
        $draftPlan = MeetingPack::factory()->create([
            'status' => MeetingPackStatus::Draft->value,
            'created_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // 下書き -> 公開 (成功)
        $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.publish', $draftPlan))
            ->assertRedirect();

        $this->assertEquals(MeetingPackStatus::Published, $draftPlan->fresh()->status);
    }

    public function test_published_pack_deletion_is_guarded(): void
    {
        $publishedPlan = MeetingPack::factory()->create([
            'status' => MeetingPackStatus::Published->value,
            'created_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // 削除を試みるがポリシーで403になること
        $this->actingAs($this->admin)
            ->delete(route('admin.meeting-packs.destroy', $publishedPlan))
            ->assertStatus(403);

        // データベースにデータが残っていること
        $this->assertDatabaseHas('meeting_packs', ['id' => $publishedPlan->id]);
    }

    // 前日リマインダー（--window=eve）の配信と重複防止のテスト
    public function test_send_meeting_reminders_eve_successfully_and_prevents_duplicate(): void
    {
        // テスト内の現在時刻を固定
        Carbon::setTestNow(Carbon::create(2026, 11, 6, 7, 24));

        // テストデータの作成(受講生、コーチ、明日12時の予約済み面談)
        $student = User::factory()->create(['status' => UserStatus::InProgress]);
        $coach = User::factory()->create(['status' => UserStatus::InProgress]);

        $meeting = Meeting::factory()->create([
            'status' => MeetingStatus::Reserved->value,
            'scheduled_at' => Carbon::now()->addDay(),
            'coach_id' => $coach->id,
            'student_id' => $student->id,
        ]);

        // コマンド実行
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0);

        // . 送信履歴レコードが作られているか確認。
        $this->assertDatabaseHas('meeting_reminders', [
            'meeting_id' => $meeting->id,
            'window' => 'eve',
        ]);

        // 受講生とコーチが保存されているか確認
        $this->assertDatabaseCount('notifications', 2);

        // 重複防止の検証
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0);

        // 重複していないか確認
        $this->assertDatabaseCount('notifications', 2);

        Carbon::setTestNow();
    }

    // 1時間後のリマインダーテスト
    public function test_send_reminders_one_hour_before_successfully(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 6, 13, 00));

        // テストデータの作成(受講生、コーチ、予約済み面談)
        $student = User::factory()->create(['status' => UserStatus::InProgress]);
        $coach = User::factory()->create(['status' => UserStatus::InProgress]);

        $meeting = Meeting::factory()->create([
            'status' => MeetingStatus::Reserved->value,
            'scheduled_at' => Carbon::now()->addMinutes(60),
            'coach_id' => $coach->id,
            'student_id' => $student->id,
        ]);

        // コマンド実行
        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0);

        // . 送信履歴レコードが作られているか確認。
        $this->assertDatabaseHas('meeting_reminders', [
            'meeting_id' => $meeting->id,
            'window' => 'one_hour_before',
        ]);

        $this->assertDatabaseCount('notifications', 2);

        Carbon::setTestNow();
    }
}
