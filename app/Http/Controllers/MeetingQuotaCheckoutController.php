<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Policies\MeetingQuotaPolicy;
use App\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingQuotaCheckoutController extends Controller
{
    public function select(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((new MeetingQuotaPolicy)->purchase($user), 403);

        $plans = MeetingPack::published()->ordered()->get();

        return view('meeting-quota.checkout-select', [
            'plans' => $plans,
        ]);
    }

    public function create(Request $request, StripeCheckoutService $stripeService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((new MeetingQuotaPolicy)->purchase($user), 403);

        $validated = $request->validate([
            'meeting_pack_id' => ['required', 'string'],
        ]);

        // 公開中（published）以外の面談パックは URL / POST 直接指定でも購入不可
        $pack = MeetingPack::query()
            ->where('id', $validated['meeting_pack_id'])
            ->where('status', MeetingPackStatus::Published)
            ->firstOrFail();

        $payment = Payment::create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'quantity' => $pack->meeting_count,
            'amount' => $pack->price,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
        ]);

        $successUrl = route('meeting-quota.success').'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('meeting-quota.checkout.select');

        $session = $stripeService->createSession(
            user: $user,
            pack: $pack,
            payment: $payment,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
        );

        $payment->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return redirect()->away((string) $session->url);
    }

    public function success(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((new MeetingQuotaPolicy)->purchase($user), 403);

        $sessionId = $request->query('session_id');
        $payment = null;

        if (is_string($sessionId) && $sessionId !== '') {
            $payment = Payment::query()
                ->with('meetingPack')
                ->where('user_id', $user->id)
                ->where('stripe_checkout_session_id', $sessionId)
                ->first();
        }

        return view('meeting-quota.success', [
            'payment' => $payment,
        ]);
    }
}
