<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\AnnouncementTargetType;
use App\Models\User;
use App\Models\Certification;
use App\Models\Announcement;
use App\Models\Notification;
use Illuminate\Support\Str;


class AnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countAllAnnouncements = 4;
        $countCertificationAnnouncements = 8;
        $countUserAnnouncements = 9;

        $students = User::where('role', UserRole::Student->value)->get();
        $certifications = Certification::all();
        $admin = User::where('role', UserRole::Admin)->first();

        if ($students->isEmpty() || $certifications->isEmpty()) {
            return;
        }

        for($i = 1; $i <= $countAllAnnouncements; $i++) {
            $announcement = Announcement::create([
                'id' => (string) Str::ulid(),
                'title' => "【全体用】運営からの全体連絡です。",
                'body' => "対象の方は、別途ご連絡をお願いいたします。",
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'dispatched_count' => $students->count(),
                'dispatched_at' => now()->subDays($i + 3),
                'created_by' => $admin->id,
            ]);
            
            foreach ($students as $user) {
                $this->createNotification($user->id, $announcement);
            }
        }

        for ($i = 1; $i <= $countCertificationAnnouncements; $i++) {
            $targetCertifications = $certifications[($i - 1) % $certifications->count()];

            $certStudents = User::where('role', UserRole::Student->value)
                ->whereHas('enrollments', function ($q) use ($targetCertifications) {
                    $q->where('certification_id', $targetCertifications->id);
                })->get();


            $announcement = Announcement::create([
                    'id' => (string) Str::ulid(),
                    'title' => "【資格者限定】「{$targetCertifications->name}」対象のお知らせ",
                    'body' => "この資格を受講している方限定でお送りするおしらせです。",
                    'target_type' => AnnouncementTargetType::Certification->value,
                    'certification_id' => $targetCertifications->id,
                    'dispatched_count' => $certStudents->count(),
                    'dispatched_at' => now()->subDays($i + 2),
                    'created_by' => $admin->id,
            ]);

            foreach ($certStudents as $user) {
                    $this->createNotification($user->id, $announcement);
            }
        }

        for ($i = 1; $i <= $countUserAnnouncements; $i++) {

            $randomStudent = $students->random();

            $announcement = Announcement::create([
                'id' => (string) Str::ulid(),
                'title' => "【個別連絡】重要なお知らせ",
                'body' => "特定の受講生宛の本文です。",
                'target_type' => AnnouncementTargetType::User->value,
                'user_id' => $randomStudent->id,
                'dispatched_count' => 1,
                'dispatched_at' => now()->subHours($i),
                'created_by' => $admin->id,
            ]);

            $this->createNotification($randomStudent->id, $announcement);
        }
    }

    private function createNotification(string $userId, Announcement $announcement): void
    {
        Notification::create([
            'id' => (string) Str::ulid(),
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'data' => [
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'notification_type' => 'admin_announcement',
            ],
            'read_at' => null,
        ]);
    }
}
