<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Database\Seeder;

class GoogleCredentialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coaches = User::query()
            ->where('role', UserRole::Coach->value)
            ->orderBy('created_at')
            ->get();

        while ($coaches->count() < 2) {
            $coaches->push(User::factory()->create([
                'role' => UserRole::Coach,
            ]));
        }

        $connectedCoach = $coaches[0];
        $unconnectedCoach = $coaches[1];

        // 1人目：連携済コーチにする
        GoogleCredential::updateOrCreate(
            ['user_id' => $connectedCoach->id],
            [
                'calendar_id' => 'primary',
                'access_token' => 'dummy-access-token',
                'refresh_token' => 'dummy-refresh-token',
                'expires_at' => now()->addHour(),
                'connected_at' => now()->subDay(),
            ]
        );

        // 2人目：未連携コーチにする（既存があれば削除して未連携状態を保証）
        GoogleCredential::where('user_id', $unconnectedCoach->id)->delete();
    }
}
