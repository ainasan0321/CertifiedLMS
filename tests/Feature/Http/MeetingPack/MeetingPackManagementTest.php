<?php

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;
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


}
