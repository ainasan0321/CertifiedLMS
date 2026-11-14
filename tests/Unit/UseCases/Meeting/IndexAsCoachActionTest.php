<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_status_student_and_enrollment(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $enrollment1 = Enrollment::factory()->for($student1, 'user')->learning()->create();
        $enrollment2 = Enrollment::factory()->for($student2, 'user')->learning()->create();

        $m1 = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student1)->create([
            'enrollment_id' => $enrollment1->id,
            'scheduled_at' => now()->addDays(2)->startOfHour(),
        ]);
        $m2 = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student2)->create([
            'enrollment_id' => $enrollment2->id,
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $past = Meeting::factory()->completed()->forCoach($coach)->forStudent($student1)->create([
            'enrollment_id' => $enrollment1->id,
            'scheduled_at' => now()->subDays(2)->startOfHour(),
        ]);
        Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student1)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $action = app(IndexAsCoachAction::class);

        // student と enrollment で絞り込み
        $res = $action($coach, [
            'filter' => 'upcoming',
            'student' => $student1->id,
            'enrollment' => $enrollment1->id,
        ]);

        $this->assertSame('upcoming', $res['filter']);
        $this->assertSame($student1->id, $res['studentFilter']);
        $this->assertSame($enrollment1->id, $res['enrollmentFilter']);
        $this->assertCount(1, $res['meetings']);
        $this->assertSame($m1->id, $res['meetings']->first()->id);

        // past フィルタ
        $resPast = $action($coach, ['filter' => 'past']);
        $this->assertCount(1, $resPast['meetings']);
        $this->assertSame($past->id, $resPast['meetings']->first()->id);
    }
}
