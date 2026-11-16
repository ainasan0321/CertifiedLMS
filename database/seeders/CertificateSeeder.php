<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

/**
 * 卒業生向けの修了証 + 過去 Enrollment 補完シーダー。
 *
 * `EnrollmentSeeder::issueCertificate()` は in_progress 受講生の passed Enrollment に対し
 * 既に Certificate を発行している(動作確認用 demo)。本 Seeder はそれを残しつつ、
 * `UserSeeder` で生成される graduated 受講生 3 名に過去 Enrollment + Certificate を補完して
 * 「修了 → 卒業」フローの履歴を画面で見える状態にする。
 */
final class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $graduatedStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Graduated->value)
            ->orderBy('created_at')
            ->get();

        if ($graduatedStudents->isEmpty()) {
            $this->command?->info('CertificateSeeder: graduated 受講生が存在しないため補完なし。');
        } else {
            $publishedCertifications = Certification::query()
                ->where('status', CertificationStatus::Published->value)
                ->orderBy('created_at')
                ->get();

            if ($publishedCertifications->isEmpty()) {
                $this->command?->warn('CertificateSeeder: 公開済資格がありません。先に CertificationSeeder を実行してください。');
            } else {
                foreach ($graduatedStudents as $i => $student) {
                    $certification = $publishedCertifications->get($i % $publishedCertifications->count());
                    if ($certification === null) {
                        continue;
                    }

                    $enrollment = $this->createPastEnrollment($student, $certification, $i);
                    $this->issueCertificateForEnrollment($enrollment);
                }
            }
        }

        $this->generateMissingPdfFiles();
    }

    /**
     * graduated 受講生に紐づく過去 Enrollment を作る(passed 状態、試験日も plan_expires_at の前)。
     * EnrollmentStatusLog も併せて積む。
     */
    private function createPastEnrollment(User $student, Certification $certification, int $orderIndex): Enrollment
    {
        $planExpiresAt = $student->plan_expires_at ?? now()->subDays(30 + $orderIndex * 15);
        $examDate = $planExpiresAt->copy()->subDays(10)->toDateString();
        $passedAt = $planExpiresAt->copy()->subDays(7);
        $startedAt = $planExpiresAt->copy()->subDays(90);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->state([
                'status' => EnrollmentStatus::Passed->value,
                'current_term' => TermType::MockPractice->value,
                'exam_date' => $examDate,
                'passed_at' => $passedAt,
            ])
            ->create();

        $enrollment->forceFill(['created_at' => $startedAt, 'updated_at' => $passedAt])->save();

        EnrollmentStatusLog::factory()->for($enrollment)->create([
            'from_status' => null,
            'to_status' => EnrollmentStatus::Learning->value,
            'changed_by_user_id' => $student->id,
            'changed_at' => $startedAt,
            'changed_reason' => '新規登録',
        ]);

        EnrollmentStatusLog::factory()->for($enrollment)->create([
            'from_status' => EnrollmentStatus::Learning->value,
            'to_status' => EnrollmentStatus::Passed->value,
            'changed_by_user_id' => $student->id,
            'changed_at' => $passedAt,
            'changed_reason' => '受講生による修了証受領',
        ]);

        return $enrollment;
    }

    /**
     * Certificate を 1 件発行する。
     */
    private function issueCertificateForEnrollment(Enrollment $enrollment): void
    {
        $issuedAt = $enrollment->passed_at ?? now();

        Certificate::factory()
            ->forEnrollment($enrollment)
            ->state([
                'pdf_path' => 'certificates/'.Str::ulid().'.pdf',
                'issued_at' => $issuedAt,
            ])
            ->create();
    }

    /**
     * 保管領域に PDF 実体が存在しない修了証すべてに対して、日本語修了証 PDF を生成・保存する。
     */
    private function generateMissingPdfFiles(): void
    {
        $disk = Storage::disk('private');

        $certificates = Certificate::query()
            ->with(['user', 'certification'])
            ->get();

        foreach ($certificates as $certificate) {
            if ($disk->exists($certificate->pdf_path)) {
                continue;
            }

            $html = view('certificates.pdf', [
                'certificate' => $certificate,
            ])->render();

            $mpdf = new Mpdf([
                'mode' => 'ja',
                'format' => 'A4-L',
                'tempDir' => storage_path('app/mpdf-tmp'),
            ]);
            $mpdf->WriteHTML($html);
            $disk->put($certificate->pdf_path, $mpdf->Output('', 'S'));
        }
    }
}
