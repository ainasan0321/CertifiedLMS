<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Certificate\IssueAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CertificateDownloadTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_issue_action_generates_and_stores_pdf_on_private_disk(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => EnrollmentStatus::Passed,
            'passed_at' => now(),
        ]);

        $certificate = app(IssueAction::class)($enrollment);

        Storage::disk('private')->assertExists($certificate->pdf_path);
        $content = Storage::disk('private')->get($certificate->pdf_path);
        $this->assertStringStartsWith('%PDF-', (string) $content);
    }

    public function test_certificate_is_rolled_back_when_pdf_storage_fails(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => EnrollmentStatus::Passed,
            'passed_at' => now(),
        ]);

        Storage::shouldReceive('disk')
            ->with('private')
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once()
            ->andReturn(false);

        try {
            app(IssueAction::class)($enrollment);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('certificates', 0);
        }
    }

    public function test_student_can_download_own_certificate_even_when_graduated(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Graduated,
        ]);
        $certificate = $this->createCertificateWithDummyPdf($student);

        $response = $this->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertDownload("certificate_{$certificate->id}.pdf");
    }

    public function test_other_student_cannot_download_certificate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Student]);
        $otherStudent = User::factory()->create(['role' => UserRole::Student]);
        $certificate = $this->createCertificateWithDummyPdf($owner);

        $this->actingAs($otherStudent)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_download_and_unassigned_coach_cannot(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $assignedCoach = User::factory()->create(['role' => UserRole::Coach]);
        $unassignedCoach = User::factory()->create(['role' => UserRole::Coach]);
        $student = User::factory()->create(['role' => UserRole::Student]);

        $certification = Certification::factory()->create();
        $certification->coaches()->attach($assignedCoach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $certificate = $this->createCertificateWithDummyPdf($student, $certification);

        $this->actingAs($assignedCoach)
            ->get(route('certificates.download', $certificate))
            ->assertOk();

        $this->actingAs($unassignedCoach)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = User::factory()->create(['role' => UserRole::Student]);
        $certificate = $this->createCertificateWithDummyPdf($student);

        $this->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertOk();
    }

    public function test_returns_404_when_pdf_file_is_missing_in_storage(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => EnrollmentStatus::Passed,
            'passed_at' => now(),
        ]);
        $certificate = Certificate::factory()->forEnrollment($enrollment)->create([
            'pdf_path' => 'certificates/missing.pdf',
        ]);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    }

    private function createCertificateWithDummyPdf(User $student, ?Certification $certification = null): Certificate
    {
        $certification ??= Certification::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => EnrollmentStatus::Passed,
            'passed_at' => now(),
        ]);

        $path = 'certificates/'.Str::ulid().'.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 dummy');

        return Certificate::factory()->forEnrollment($enrollment)->create([
            'pdf_path' => $path,
        ]);
    }
}
