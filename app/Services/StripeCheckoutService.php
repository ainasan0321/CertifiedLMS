<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function createSession(
        User $user,
        MeetingPack $pack,
        Payment $payment,
        string $successUrl,
        string $cancelUrl,
    ): Session {
        $client = new StripeClient((string) config('services.stripe.secret'));

        return $client->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $user->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $pack->price,
                    'product_data' => [
                        'name' => $pack->name,
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'meeting_pack_id' => $pack->id,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }
}
