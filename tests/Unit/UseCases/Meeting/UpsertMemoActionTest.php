<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_and_updates_memo_for_valid_meeting(): void
    {
        $meeting = Meeting::factory()->completed()->create();
        $action = new UpsertMemoAction;

        // 新規作成
        $memo = $action($meeting, '初回メモ');
        $this->assertSame('初回メモ', $memo->body);
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '初回メモ',
        ]);

        // 更新
        $updated = $action($meeting, '更新後メモ');
        $this->assertSame($memo->id, $updated->id);
        $this->assertSame('更新後メモ', $updated->body);
    }

    public function test_throws_exception_when_meeting_is_canceled(): void
    {
        $meeting = Meeting::factory()->canceled()->create();

        $this->expectException(MeetingStatusTransitionException::class);

        (new UpsertMemoAction)($meeting, 'キャンセル済メモ');
    }
}
