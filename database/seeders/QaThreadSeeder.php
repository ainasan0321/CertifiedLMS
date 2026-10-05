<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QaThread;
use App\Models\QaReply;
use App\Models\User;
use App\Models\Certification;
use App\Enums\QaThreadStatus;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class QaThreadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = User::where('role', 'student')->first();

        $otherStudent = User::where('role', 'student')->skip(1)->first() ?? $student;

        $coach = User::where('role', 'coach')->first();

        $certifications = Certification::published()->get();

        if (!$student || $certifications->isEmpty()) {
            return;
        }

        $now = Carbon::now();

        $count = 1;

        foreach($certifications as $cert) {
            $thread = QaThread::create([
                'certification_id' => $cert->id,
                'user_id' => $student->id,
                'title' => "未解決の質問テスト (No.{$count})",
                'body' => "未解決のスレッドです。",
                'status' => QaTreadStatus::Unresolved->value,
                'created_at' => $now->copy()->subHours($count),
                'updated_at' => $now->copy()->subHours($count),
            ]);
            $count++;

            if($coach) {
                QaReply::create([
                    'qa_thread_id' => $thread1->id,
                    'user_id' => $coach->id,
                    'body' => 'コーチからの回答です。',
                ]);
            }
        }

        QaTread::create([
            'certification_id' => $cert->id,
            'user_id' => $student->id,
            'title' => "解決済の質問テスト (No.{$count})",
            'body' => "解決済のスレッドです。",
            'status' => QaTreadStatus::Resolved->value,
            'created_at' => $now->copy()->subDays($count),
            'updated_at' => $now->copy()->subDays($count),
        ]);
        $count++;

        $firstCert = $certifications->first(); // 1つ目の資格を使い回す

        for ($i = 1; $i <= 15; $i++) {
            QaThread::create([
                'certification_id' => $firstCert->id,
                'user_id' => $otherStudent->id,
                'title' => "【ページネーション確認用】追加の未解決質問 (増量No.{$i})",
                'body' => "ページネーション（1ページ20件制限）の動作を確認するため、自動増量された質問です。回答数は0件です。",
                'status' => QaThreadStatus::Unresolved->value,
                'created_at' => $now->copy()->subDays($count + $i), // さらに過去の日時にずらす（並び順の確認用）
                'updated_at' => $now->copy()->subDays($count + $i),
            ]);
        }
    }
}
