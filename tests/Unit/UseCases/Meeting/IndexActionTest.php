<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    /**
     * A basic unit test example.
     */

    use RefreshDatabase;

public function test_filters_upcoming_past_and_all_meetings_and_returns_remaining_quota(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();

        $upcoming = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(2)->startOfHour(),
        ]);
        $past = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(2)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $action = app(IndexAction::class);

        // upcoming (デフォルト)
        $resUpcoming = $action($student);
        $this->assertSame('upcoming', $resUpcoming['filter']);
        $this->assertSame(5, $resUpcoming['meetingsRemaining']);
        $this->assertTrue($resUpcoming['meetings']->contains('id', $upcoming->id));
        $this->assertFalse($resUpcoming['meetings']->contains('id', $past->id));
        $this->assertFalse($resUpcoming['meetings']->contains('id', $other->id));

        // past
        $resPast = $action($student, 'past');
        $this->assertSame('past', $resPast['filter']);
        $this->assertTrue($resPast['meetings']->contains('id', $past->id));
        $this->assertFalse($resPast['meetings']->contains('id', $upcoming->id));

        // all
        $resAll = $action($student, 'all');
        $this->assertSame('all', $resAll['filter']);
        $this->assertCount(2, $resAll['meetings']);
    }
}
