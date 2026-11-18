<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');
        $webhookSecret = (string) config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Invalid signature', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response('Ignored', 200);
        }

        /** @var Session $session */
        $session = $event->data->object;

        if (($session->payment_status ?? null) !== 'paid') {
            return response('Unpaid', 200);
        }

        $paymentId = $session->metadata['payment_id'] ?? null;
        $sessionId = $session->id ?? null;
        $eventId = $event->id ?? null;

        DB::transaction(function () use ($paymentId, $sessionId, $eventId, $session): void {
            // 同一イベントIDが既に処理済みであれば何もしない
            if (is_string($eventId) && $eventId !== '' && Payment::where('stripe_event_id', $eventId)->exists()) {
                return;
            }

            if ((! is_string($paymentId) || $paymentId === '') && (! is_string($sessionId) || $sessionId === '')) {
                return;
            }

            $payment = Payment::query()
                ->lockForUpdate()
                ->where(function ($q) use ($paymentId, $sessionId): void {
                    if (is_string($paymentId) && $paymentId !== '') {
                        $q->orWhere('id', $paymentId);
                    }
                    if (is_string($sessionId) && $sessionId !== '') {
                        $q->orWhere('stripe_checkout_session_id', $sessionId);
                    }
                })
                ->first();

            // 存在しない、または既に完了済みの場合は二重加算せず終了
            if (! $payment || $payment->status === PaymentStatus::Completed) {
                return;
            }

            $payment->update([
                'status' => PaymentStatus::Completed,
                'stripe_payment_intent_id' => is_string($session->payment_intent ?? null)
                    ? $session->payment_intent
                    : null,
                'stripe_event_id' => is_string($eventId) && $eventId !== '' ? $eventId : null,
                'paid_at' => now(),
            ]);

            MeetingQuotaTransaction::create([
                'user_id' => $payment->user_id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => now(),
            ]);
        });

        return response('OK', 200);
    }
}
