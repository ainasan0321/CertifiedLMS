<?php

namespace Tests\Feature\Http\Enrollment;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentNoteTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use RefreshDatabase;

    // 管理者は受講登録にメモを追加・更新・削除できる
    public function test_admin_can_manage_enrollment_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        // 追加
        $response = $this->actingAs($admin)->post(route('enrollment.notes.store', $enrollment), [
            'body' => '管理者からのテストメモ',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('enrollment_notes', ['body' => '管理者からのテストメモ']);

        $note = EnrollmentNote::first();

        // 更新
        $response = $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), [
            'body' => '管理者が更新したテストメモ',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('enrollment_notes', ['body' => '管理者が更新したテストメモ']);

        // 削除
        $response = $this->actingAs($admin)->delete(route('enrollment-notes.destroy', $note));
        $response->assertRedirect();
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    // 受講生は全てのメモ操作を拒否(403)
    public function test_student_is_forbidden_from_all_actions(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->create();

        // 既存のメモを1件用意
        $note = EnrollmentNote::create([
            'enrollment_id' => $enrollment->id,
            'author_id' => User::factory()->admin()->create()->id,
            'body' => '機密メモ',
        ]);

        // 追加拒否
        $this->actingAs($student)->post(route('enrollment.notes.store', $enrollment), ['body' => '拒否されるはず'])
            ->assertStatus(403);

        // 編集画面拒否
        $this->actingAs($student)->get(route('enrollment-notes.edit', $note))
            ->assertStatus(403);

        // 更新拒否
        $this->actingAs($student)->patch(route('enrollment-notes.update', $note), ['body' => '拒否'])
            ->assertStatus(403);

        // 削除拒否
        $this->actingAs($student)->delete(route('enrollment-notes.destroy', $note))
            ->assertStatus(403);
    }
}
