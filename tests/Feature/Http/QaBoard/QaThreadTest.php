<?php

namespace Tests\Feature\Http\QaBoard;

use App\Models\User;
use App\Models\QaThread;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Certification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class QaThreadTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use RefreshDatabase;

    public function test_authenticated_user_can_create_qa_thread(): void
    {
        $user = User::factory()->student()->create();
        $certification = Certification::factory()->create();

        // 1. 質問投稿の実行（ルート名は実際の実装に合わせてください）
        $response = $this->actingAs($user)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'Laravelのルーティングについて質問です',
            'body' => 'Route::postの第二引数に渡すコントローラーの記述方法がわかりません。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_threads', [
            'title' => 'Laravelのルーティングについて質問です'
        ]);
    }

    public function test_guest_cannot_create_qa_thread(): void
    {
        $this->post(route('qa-board.store'), [
            'title' => '未ログインの投稿',
            'body' => '投稿できないはずです。',
        ])->assertRedirect(route('login'));
    }

    public function test_author_can_edit_and_delete_their_own_thread(): void
    {
        $author = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        
        // 💡 投稿者本人に紐付けて質問を作成
        $thread = QaThread::create([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
            'title' => '本人の質問タイトル',
            'body' => '本人の質問本文',
        ]);

        // 1. 編集画面へのアクセス成功
        $this->actingAs($author)->get(route('qa-board.edit', $thread))
            ->assertStatus(200);

        // 2. 更新の成功
        $this->actingAs($author)->patch(route('qa-board.update', $thread), [
            'title' => '更新されたタイトル',
            'body' => '更新された本文',
        ])->assertRedirect();

        // 3. 削除の成功
        $this->actingAs($author)->delete(route('qa-board.destroy', $thread))
            ->assertRedirect();
            
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_other_users_can_only_view_and_cannot_edit_or_delete_thread(): void
    {
        $author = User::factory()->student()->create();
        $otherUser = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
        ]);
        Enrollment::factory()->create([
            'user_id' => $otherUser->id,
            'certification_id' => $certification->id,
        ]);

        // 投稿者（$author）の質問を用意
        $thread = QaThread::create([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
            'title' => '他人の質問タイトル',
            'body' => '他人の質問本文',
        ]);

        // 1. 他のユーザーでも詳細画面の「閲覧」はできる（200 OK）
        $this->actingAs($otherUser)->get(route('qa-board.show', ['thread' => $thread->id]))
            ->assertStatus(200);

        // 2. 他のユーザーが編集画面を開こうとすると拒否される（403 Forbidden）
        $this->actingAs($otherUser)->get(route('qa-board.edit', ['thread' => $thread->id]))
            ->assertStatus(403);

        // 3. 他のユーザーが更新しようとすると拒否される（403 Forbidden）
        $this->actingAs($otherUser)->patch(route('qa-board.update', ['thread' => $thread->id]), [
            'certification_id' => $certification->id,
            'title' => '不正な更新',
            'body' => '不正な本文',
        ])->assertStatus(403);

        // 4. 他のユーザーが削除しようとすると拒否される（403 Forbidden）
        $this->actingAs($otherUser)->delete(route('qa-board.destroy', ['thread' => $thread->id]))
            ->assertStatus(403);

        // データベースに質問がしっかり残っていることを検証
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }
}
