<?php

namespace Tests\Feature\Http\Enrollment;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\Certification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EnrollmentGoalTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use RefreshDatabase;

    public function test_student_can_manage_their_own_goals(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '今週の学習目標',
            'description' => 'カリキュラムを2章まで進める',
            'target_date' => now()->addDays(7)->format('Y-m-d'),
        ]);
        $response->assertRedirect();
        
        $this->assertDatabaseHas('enrollment_goals', ['title' => '今週の学習目標']);
    }
}
