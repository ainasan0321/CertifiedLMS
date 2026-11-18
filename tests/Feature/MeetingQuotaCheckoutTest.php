<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\MeetingQuotaService;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session;
use Tests\TestCase;

class MeetingQuotaCheckoutTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    private function createStudent(UserStatus $status = UserStatus::InProgress, int $maxMeetings = 2): User
    {
        return User::factory()->create([
            'role' => UserRole::Student,
            'status' => $status,
            'max_meetings' => $maxMeetings,
            'profile_setup_completed' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createPack(MeetingPackStatus $status = MeetingPackStatus::Published, int $count = 5, int $price = 12000): MeetingPack
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::InProgress,
        ]);

        return MeetingPack::create([
            'name' => "{$count} 回パック",
            'description' => 'テスト用面談パック',
            'meeting_count' => $count,
            'price' => $price,
            'status' => $status,
            'sort_order' => 10,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    private function buildStripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_active_student_can_view_only_published_packs(): void
    {
        $student = $this->createStudent();
        $published = $this->createPack(MeetingPackStatus::Published, 5, 12000);
        $draft = $this->createPack(MeetingPackStatus::Draft, 3, 7000);
        $archived = $this->createPack(MeetingPackStatus::Archived, 10, 20000);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        $response->assertOk();
        $response->assertSee($published->name);
        $response->assertDontSee($draft->name);
        $response->assertDontSee($archived->name);
    }

    public function test_non_active_student_and_other_roles_cannot_access_checkout(): void
    {
        $graduated = $this->createStudent(UserStatus::Graduated);
        $coach = User::factory()->create(['role' => UserRole::Coach, 'status' => UserStatus::InProgress]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::InProgress]);

        foreach ([$graduated, $coach, $admin] as $user) {
            $this->actingAs($user)
                ->get(route('meeting-quota.checkout.select'))
                ->assertForbidden();
        }
    }

    public function test_cannot_purchase_non_published_pack_even_with_direct_id(): void
    {
        $student = $this->createStudent();
        $draft = $this->createPack(MeetingPackStatus::Draft, 3, 7000);

        $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $draft->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_active_student_can_create_checkout_session_and_redirect_to_stripe(): void
    {
        $student = $this->createStudent();
        $pack = $this->createPack(MeetingPackStatus::Published, 5, 12000);

        $fakeSession = Session::constructFrom([
            'id' => 'cs_test_123456',
            'url' => 'https://stripe.com',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('createSession')->once()->andReturn($fakeSession);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        $response->assertRedirect('https://stripe.com');

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'quantity' => 5,
            'amount' => 12000,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => 'cs_test_123456',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $payload = json_encode(['id' => 'evt_invalid', 'type' => 'checkout.session.completed'], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 't=12345,v1=invalid_sig', 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertStatus(400);
    }

    public function test_webhook_adds_quota_on_checkout_completed_and_prevents_duplicate_addition(): void
    {
        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook_secret' => $secret]);

        $student = $this->createStudent(UserStatus::InProgress, 2);
        $pack = $this->createPack(MeetingPackStatus::Published, 5, 12000);

        $payment = Payment::create([
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'quantity' => 5,
            'amount' => 12000,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_completed_1',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_completed_1',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_1',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $sigHeader = $this->buildStripeSignature($payload, $secret);

        // 1回目の Webhook 受信 → 残数が 2 + 5 = 7 になる
        $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $sigHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertOk();

        $quotaService = app(MeetingQuotaService::class);
        $this->assertSame(7, $quotaService->remaining($student));
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Completed->value,
            'stripe_event_id' => 'evt_test_1',
        ]);

        // 2回目の重複 Webhook 受信（別 event_id でも同一 payment_id / session_id）→ 二重加算されない
        $duplicatePayload = json_encode([
            'id' => 'evt_test_2_duplicate',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_completed_1',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_1',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $this->buildStripeSignature($duplicatePayload, $secret), 'CONTENT_TYPE' => 'application/json'],
            $duplicatePayload,
        )->assertOk();

        $this->assertSame(7, $quotaService->remaining($student));
        $this->assertDatabaseCount('meeting_quota_transactions', 1);
    }

    public function test_webhook_ignores_unexpected_events_gracefully(): void
    {
        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook_secret' => $secret]);

        $payload = json_encode([
            'id' => 'evt_other_1',
            'type' => 'payment_intent.created',
            'data' => ['object' => ['id' => 'pi_123']],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $this->buildStripeSignature($payload, $secret), 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertOk();
    }
}
