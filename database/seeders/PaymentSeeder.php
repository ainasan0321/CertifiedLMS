<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingPackStatus;
use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packs = MeetingPack::query()
            ->where('status', MeetingPackStatus::Published->value)
            ->orderBy('sort_order')
            ->get();

        if ($packs->isEmpty()) {
            $this->command?->warn('PaymentSeeder: 公開中の MeetingPack が存在しません。先に MeetingPackSeeder を実行してください。');

            return;
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', ['student@certify-lms.test', 'student-noquota@certify-lms.test'])
            ->orderBy('created_at')
            ->take(3)
            ->get();

        $targets = collect([$fixedStudent])
            ->filter()
            ->merge($demoStudents);

        foreach ($targets as $index => $student) {
            $pack1 = $packs[$index % $packs->count()];
            $pack2 = $packs[($index + 1) % $packs->count()];

            // 1. 決済完了(completed): 残面談回数に反映される
            $completedPayment = Payment::create([
                'user_id' => $student->id,
                'meeting_pack_id' => $pack1->id,
                'quantity' => $pack1->meeting_count,
                'amount' => $pack1->price,
                'currency' => 'jpy',
                'status' => PaymentStatus::Completed,
                'stripe_checkout_session_id' => 'cs_test_seed_completed_'.Str::lower((string) Str::ulid()),
                'stripe_payment_intent_id' => 'pi_test_seed_'.Str::lower((string) Str::ulid()),
                'stripe_event_id' => 'evt_test_seed_'.Str::lower((string) Str::ulid()),
                'paid_at' => now()->subDays(5 - $index),
            ]);

            MeetingQuotaTransaction::create([
                'user_id' => $student->id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $completedPayment->quantity,
                'related_payment_id' => $completedPayment->id,
                'occurred_at' => $completedPayment->paid_at,
            ]);

            // 2. 決済保留(pending): 残面談回数には反映されない
            Payment::create([
                'user_id' => $student->id,
                'meeting_pack_id' => $pack2->id,
                'quantity' => $pack2->meeting_count,
                'amount' => $pack2->price,
                'currency' => 'jpy',
                'status' => PaymentStatus::Pending,
                'stripe_checkout_session_id' => 'cs_test_seed_pending_'.Str::lower((string) Str::ulid()),
                'paid_at' => null,
            ]);

            // 3. 決済失敗(failed): 残面談回数には反映されない
            Payment::create([
                'user_id' => $student->id,
                'meeting_pack_id' => $pack1->id,
                'quantity' => $pack1->meeting_count,
                'amount' => $pack1->price,
                'currency' => 'jpy',
                'status' => PaymentStatus::Failed,
                'stripe_checkout_session_id' => 'cs_test_seed_failed_'.Str::lower((string) Str::ulid()),
                'paid_at' => null,
            ]);
        }
    }
}
