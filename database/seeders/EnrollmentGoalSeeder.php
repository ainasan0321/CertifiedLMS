<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Seeder;

class EnrollmentGoalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = User::where('role', \App\Enums\UserRole::Student)->get();

        foreach ($students as $studentIndex => $student) {
            $enrollments = $student->enrollments()->where('status', EnrollmentStatus::Learning)->get();

            foreach ($enrollments as $enrollmentIndex => $enrollment) {
                $enrollment->goals()->delete();

                // 未達成
                for($i = 1; $i <= 3; $i++) {
                    $enrollment->goals()->create([
                            'title' => '【未達成 ' . $i . '】レッスン' . $i . 'の講義動画を視聴し、課題を提出する',
                            'description' => $student->name . 'さんの学習計画に基づく未達成目標第' . $i . '弾。',
                            'target_date' => now()->addDays(rand(2, 10)),
                            'achieved_at' => null,
                    ]);
                }

                // 達成済
                for($j = 1; $j <= 3; $j++) {
                    $achievedDate = now()->subDays(rand(1, 7));
                    $enrollment->goals()->create([
                            'title' => '【達成済 ' . $j . '】第' . $j . '章の確認クイズで満点を獲得する',
                            'description' => $student->name . 'さんが過去にクリアした達成済目標第' . $j . '弾。',
                            'target_date' => $achievedDate,
                            'achieved_at' => $achievedDate,
                    ]);
                }
            }
        }
    }
}
