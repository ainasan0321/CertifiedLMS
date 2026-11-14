<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Meeting;
use App\UseCases\Meeting\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_required_relations(): void
    {
        $meeting = Meeting::factory()->reserved()->create();
        $freshMeeting = Meeting::findOrFail($meeting->id);

        $result = (new ShowAction)($freshMeeting);

        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue($result->enrollment->relationLoaded('certification'));
        $this->assertTrue($result->relationLoaded('coach'));
        $this->assertTrue($result->relationLoaded('student'));
        $this->assertTrue($result->relationLoaded('canceledBy'));
        $this->assertTrue($result->relationLoaded('meetingMemo'));
    }
}
