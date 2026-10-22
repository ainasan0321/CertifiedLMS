<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\Certification;
use App\Enums\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Seeder;

class EnrollmentNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 既存のユーザーを取得
        // 管理者
        $admin = User::where('role', UserRole::Admin)->first()
            ?? User::factory()->admin()->create(['name' => '管理者 太郎']);

        // コーチ
        $coachMan = User::where('role', UserRole::Coach)->first()
            ?? User::factory()->coach()->create(['name' => '男性コーチ']);
        $coachWoman = User::where('role', UserRole::Coach)->skip(1)->first()
            ?? User::factory()->coach()->create(['name' => '女性コーチ']);

        // 受講生
        $studentMan = User::where('role', UserRole::Student)->first()
            ?? User::factory()->student()->create(['name' => '男性受講生']);
        $studentWoman = User::where('role', UserRole::Student)->skip(1)->first()
            ?? User::factory()->student()->create(['name' => '女性受講生B']);

        // 資格
        $certificationA = Certification::first()
            ?? Certification::factory()->published()->create(['name' => 'テスト 資格A']);
        $certificationB = Certification::skip(1)->first()
            ?? Certification::factory()->published()->create(['name' => 'テスト 資格B']);

        // コーチと資格を紐付け
        $coachMan->assignedCertifications()->syncWithoutDetaching([
            $certificationA->id => [
                'assigned_at' => now(),
                'assigned_by_user_id' => $admin->id,
        ]]);

        $coachWoman->assignedCertifications()->syncWithoutDetaching([
            $certificationA->id => [
                'assigned_at' => now(),
                'assigned_by_user_id' => $admin->id,
            ],
            $certificationB->id => [
                'assigned_at' => now(),
                'assigned_by_user_id' => $admin->id,
            ],
        ]);

        // 複数の受講登録に紐付け
        $enrollmentA = Enrollment::firstOrCreate([
            'user_id' => $studentMan->id,
            'certification_id' => $certificationA->id
        ],['status' => EnrollmentStatus::Learning->value
        ]);

        $enrollmentB = Enrollment::firstOrCreate([
            'user_id' => $studentWoman->id,
            'certification_id' => $certificationB->id
        ],['status' => EnrollmentStatus::Learning->value
        ]);

        // データ重複を防ぐため古いメモはクリア
        $enrollmentIds = [$enrollmentA->id, $enrollmentB->id, $enrollmentA->id];
        EnrollmentNote::whereIn('enrollment_id', $enrollmentIds)->delete();

        // メモ投入
        EnrollmentNote::create([
            'enrollment_id' => $enrollmentA->id,
            'author_id' => $coachMan->id,
            'body' => '【男性コーチ】男性受講生さんはカリキュラム通り順調に進んでいます。',
            'created_at' => now()->subDays(3),
        ]);

        EnrollmentNote::create([
            'enrollment_id' => $enrollmentA->id,
            'author_id' => $coachWoman->id,
            'body' => '【女性コーチ】男性受講生さんから、Q&Aのコードレビュー依頼を受け付けました。',
            'created_at' => now()->subDay(2),
        ]);

        EnrollmentNote::create([
            'enrollment_id' => $enrollmentA->id,
            'author_id' => $coachMan->id,
            'body' => '【男性コーチ】問5の問題の質問を確認中。',
            'created_at' => now()->subDay(),
        ]);

        EnrollmentNote::create([
            'enrollment_id' => $enrollmentA->id,
            'author_id' => $admin->id,
            'body' => '【管理者】男性受講生さんへ面談スケジュールの確認メールを送信済み。',
            'created_at' => now(),
        ]);

        EnrollmentNote::create([
            'enrollment_id' => $enrollmentB->id,
            'author_id' => $coachWoman->id,
            'body' => '【女性コーチ資格2】課題未提出。再度確認必要。',
            'created_at' => now()->subDays(2),
        ]);

        EnrollmentNote::create([
            'enrollment_id' => $enrollmentB->id,
            'author_id' => $coachWoman->id,
            'body' => '【女性コーチ資格2】課題本日提出。コーチ内で確認必要。',
            'created_at' => now(),
        ]);

        EnrollmentNote::create([
            'enrollment_id' => $enrollmentA->id,
            'author_id' => $coachWoman->id,
            'body' => '【女性コーチ資格1】女初回日常観察。やる気は非常に高い。',
            'created_at' => now()->subDays(2),
        ]);

        EnrollmentNote::create([
            'enrollment_id' => $enrollmentA->id,
            'author_id' => $coachMan->id,
            'body' => '【男性コーチ資格1】模擬試験のスコアが安定してきました。この調子でキープ。',
            'created_at' => now(),
        ]);
    }
}
