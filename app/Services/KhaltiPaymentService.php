<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Server-side integration with Khalti's e-Payment (Web Checkout) API.
 * Source of truth: https://docs.khalti.com/khalti-epayment/
 *
 * Flow: initiate() calls Khalti's /epayment/initiate/ endpoint, which
 * returns a pidx (payment id) and a payment_url to redirect the customer
 * to. Khalti later redirects back to our single return route with pidx +
 * status + transaction_id + amount in the query string — none of which is
 * trusted. reconcile() asks Khalti's own /epayment/lookup/ endpoint
 * (server-to-server) for the real status; only a lookup of "Completed" for
 * exactly the amount we charged is treated as paid, as Khalti's docs
 * require ("Only the status with Completed must be treated as success").
 *
 * Lookup status handling (per Khalti's docs):
 *   Completed                      -> completed (if the amount matches)
 *   Pending / Initiated /
 *   Partially Refunded             -> stays pending ("hold")
 *   User canceled                  -> cancelled
 *   Expired / Refunded             -> failed
 *   no usable answer (network/5xx) -> unchanged; nothing is decided
 * A failed/cancelled payment is not final: if Khalti later reports it
 * Completed, reconcile() still settles it.
 *
 * Amounts: our database stores NPR as decimal(10,2); Khalti's API uses
 * integer paisa (NPR x 100). toPaisa() is the only conversion used, for
 * both the amount sent to initiate and the amount compared on lookup.
 */
class KhaltiPaymentService implements PaymentGatewayInterface
{
    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_INITIATED = 'Initiated';

    public const STATUS_USER_CANCELED = 'User canceled';

    public const STATUS_EXPIRED = 'Expired';

    public const STATUS_REFUNDED = 'Refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'Partially Refunded';

    /** Khalti statuses where the payment must be held, neither paid nor failed. */
    public const HOLD_STATUSES = [self::STATUS_PENDING, self::STATUS_INITIATED, self::STATUS_PARTIALLY_REFUNDED];

    protected string $mode;

    protected ?string $secretKey;

    public function __construct(
        protected BookingService $bookingService,
        protected PhoneNumberService $phones,
    ) {
        $this->mode = config('khalti.mode', 'sandbox');
        $this->secretKey = config('khalti.secret_key');
    }

    /**
     * The example key printed in Khalti's own public documentation. Khalti's
     * sandbox (dev.khalti.com) accepts it, so it works as a shared test key
     * the way eSewa's EPAYTEST does — but it is public, so it must never be
     * used with KHALTI_MODE=production.
     */
    public const PUBLIC_SANDBOX_KEY = 'live_secret_key_68791341fdd94846a146f0457ff7b455';

    public function isAvailable(): bool
    {
        if ($this->usesPublicKeyInProduction()) {
            Log::error('Khalti disabled: KHALTI_MODE=production is set with the public documentation test key. Use your own live key from admin.khalti.com.');

            return false;
        }

        return (bool) config('payments.methods.khalti.enabled')
            && filled($this->secretKey)
            && app(Payments\GatewayEnvironmentGuard::class)->isSafe('khalti');
    }

    protected function usesPublicKeyInProduction(): bool
    {
        return $this->mode === 'production' && $this->secretKey === self::PUBLIC_SANDBOX_KEY;
    }

    protected function baseUrl(): string
    {
        return config("khalti.urls.{$this->mode}.base");
    }

    /**
     * NPR amount as stored in the database (e.g. "4500.00") -> Khalti paisa
     * (450000). Goes through a fixed 2-decimal string, so float noise such
     * as 4500.0000001 can never shift the result by a paisa.
     */
    public static function toPaisa(string|int|float $amount): int
    {
        return (int) str_replace('.', '', number_format((float) $amount, 2, '.', ''));
    }

    /**
     * Create a pending Payment and ask Khalti to start a payment session
     * for it. The amount is always the booking's own server-calculated
     * total_price — never request input.
     *
     * @return array{payment: Payment, payment_url: string}
     *
     * @throws \RuntimeException if Khalti is not configured, the amount is
     *                           below Khalti's minimum, or Khalti rejects the request
     */
    public function initiate(Booking $booking): array
    {
        if (! filled($this->secretKey) || $this->usesPublicKeyInProduction()) {
            throw new \RuntimeException('Khalti is not configured (missing KHALTI_SECRET_KEY, or the public test key in production).');
        }

        $amountPaisa = self::toPaisa($booking->amountDueNow());

        if ($amountPaisa < (int) config('khalti.minimum_amount_paisa', 1000)) {
            Log::warning('Khalti payment not started: amount below Khalti minimum.', [
                'booking_id' => $booking->id,
                'amount_paisa' => $amountPaisa,
            ]);

            throw new \RuntimeException('The amount is below Khalti\'s minimum of Rs. 10.');
        }

        $purchaseOrderId = $booking->confirmation_code.'-'.Str::upper(Str::random(8));

        try {
            $response = Http::timeout(config('khalti.timeout', 15))->withHeaders([
                'Authorization' => "Key {$this->secretKey}",
            ])->post($this->baseUrl().'epayment/initiate/', [
                'return_url' => route('payment.khalti.return'),
                'website_url' => config('app.url'),
                'amount' => $amountPaisa,
                'purchase_order_id' => $purchaseOrderId,
                'purchase_order_name' => "Room booking {$booking->confirmation_code}",
                'customer_info' => $this->customerInfo($booking),
            ]);
        } catch (ConnectionException $e) {
            Log::error('Khalti payment initiation could not connect.', ['booking_id' => $booking->id]);

            throw new \RuntimeException('Khalti could not be reached.');
        }

        if (! $response->successful() || ! is_string($response->json('pidx')) || ! is_string($response->json('payment_url'))) {
            Log::error('Khalti payment initiation failed.', [
                'booking_id' => $booking->id,
                'status' => $response->status(),
                // Khalti's error body (validation messages / error_key) — contains no credentials.
                'body' => $response->json(),
            ]);

            throw new \RuntimeException('Khalti did not accept the payment request.');
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'khalti',
            'transaction_uuid' => $purchaseOrderId,
            'reference_id' => $response->json('pidx'),
            'amount' => $booking->amountDueNow(),
            'currency' => 'NPR',
            'status' => Payment::STATUS_PENDING,
            'gateway_response' => ['initiate' => $response->json()],
        ]);

        Log::info('Khalti payment initiated.', ['booking_id' => $booking->id, 'payment_id' => $payment->id]);

        return [
            'payment' => $payment,
            'payment_url' => $response->json('payment_url'),
        ];
    }

    /**
     * The booking's most recent Khalti payment that is still pending, if any.
     */
    public function openPayment(Booking $booking): ?Payment
    {
        return $booking->payments()
            ->where('provider', 'khalti')
            ->where('status', Payment::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * Khalti's checkout URL for a still-unpaid ("Initiated") payment, if it
     * can be safely reused instead of starting a second payment session
     * for the same booking: same amount, and the link isn't about to expire.
     */
    public function reusableCheckoutUrl(Payment $payment, Booking $booking): ?string
    {
        $initiate = $payment->gateway_response['initiate'] ?? [];
        $url = $initiate['payment_url'] ?? null;
        $expiresAt = $initiate['expires_at'] ?? null;

        if ($payment->status !== Payment::STATUS_PENDING || ! is_string($url) || ! is_string($expiresAt)) {
            return null;
        }

        if (self::toPaisa($payment->amount) !== self::toPaisa($booking->amountDueNow())) {
            return null;
        }

        try {
            if (Carbon::parse($expiresAt)->lte(now()->addMinutes(2))) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $url;
    }

    /**
     * Process the customer's return from Khalti. Only the pidx is used, to
     * find OUR payment record; everything else in the query string is
     * ignored in favour of Khalti's lookup API.
     *
     * @throws \RuntimeException if the payment cannot be identified
     */
    public function handleReturn(?string $pidx, array $query = []): KhaltiVerificationResult
    {
        if (! is_string($pidx) || $pidx === '') {
            throw new \RuntimeException('No payment reference was returned by Khalti.');
        }

        $payment = Payment::where('provider', 'khalti')->where('reference_id', $pidx)->first();

        if (! $payment) {
            Log::warning('Khalti return referenced an unknown pidx.', ['pidx' => $pidx]);

            throw new \RuntimeException('This payment could not be matched to a booking.');
        }

        if (isset($query['purchase_order_id']) && $query['purchase_order_id'] !== $payment->transaction_uuid) {
            // Tampered or mismatched query string — harmless (the lookup is
            // by pidx and decides everything) but worth knowing about.
            Log::warning('Khalti return purchase_order_id does not match the payment for this pidx.', [
                'payment_id' => $payment->id,
            ]);
        }

        return $this->reconcile($payment);
    }

    /**
     * Ask Khalti for the real status of a payment and apply it. Idempotent
     * and safe to call any number of times, from any number of requests:
     * the payment row is locked, and a completed payment is never
     * re-processed or confirmed twice.
     */
    public function reconcile(Payment $payment): KhaltiVerificationResult
    {
        if ($payment->isCompleted()) {
            return new KhaltiVerificationResult($payment, self::STATUS_COMPLETED);
        }

        // Network call outside the DB transaction, so no lock is held while waiting on Khalti.
        $lookup = $this->lookup($payment->reference_id);

        return DB::transaction(function () use ($payment, $lookup) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->isCompleted()) {
                return new KhaltiVerificationResult($payment, self::STATUS_COMPLETED);
            }

            if ($lookup === null) {
                return new KhaltiVerificationResult($payment, null);
            }

            if (isset($lookup['pidx']) && $lookup['pidx'] !== $payment->reference_id) {
                Log::error('Khalti lookup returned a different pidx than requested.', ['payment_id' => $payment->id]);

                return new KhaltiVerificationResult($payment, null);
            }

            $status = $lookup['status'];
            $payment->gateway_response = $this->withLookup($payment->gateway_response, $lookup);

            if ($status === self::STATUS_COMPLETED) {
                $gatewayPaisa = isset($lookup['total_amount']) && is_numeric($lookup['total_amount'])
                    ? (int) $lookup['total_amount']
                    : null;

                if ($gatewayPaisa !== self::toPaisa($payment->amount)) {
                    $payment->status = Payment::STATUS_FAILED;
                    $payment->save();

                    Log::error('Khalti reported Completed for a different amount than was charged — not confirming.', [
                        'payment_id' => $payment->id,
                        'booking_id' => $payment->booking_id,
                        'expected_paisa' => self::toPaisa($payment->amount),
                        'gateway_paisa' => $gatewayPaisa,
                    ]);

                    return new KhaltiVerificationResult($payment, $status);
                }

                $payment->status = Payment::STATUS_COMPLETED;
                $payment->paid_at = now();
                $payment->save();

                $this->bookingService->confirmAfterVerifiedPayment($payment);

                Log::info('Khalti payment verified and completed.', ['payment_id' => $payment->id, 'booking_id' => $payment->booking_id]);

                if ($payment->booking()->value('status') !== Booking::STATUS_CONFIRMED) {
                    Log::error('Khalti payment completed but the booking could not be confirmed (expired/cancelled or room taken) — staff must resolve (refund or rebook).', [
                        'payment_id' => $payment->id,
                        'booking_id' => $payment->booking_id,
                    ]);
                }

                return new KhaltiVerificationResult($payment, $status, justCompleted: true);
            }

            $newStatus = match ($status) {
                self::STATUS_PENDING, self::STATUS_INITIATED, self::STATUS_PARTIALLY_REFUNDED => Payment::STATUS_PENDING,
                self::STATUS_USER_CANCELED => Payment::STATUS_CANCELLED,
                self::STATUS_EXPIRED, self::STATUS_REFUNDED => Payment::STATUS_FAILED,
                default => null,
            };

            if ($newStatus === null) {
                Log::warning('Khalti lookup returned an unrecognised status — leaving payment unchanged.', [
                    'payment_id' => $payment->id,
                    'lookup_status' => $status,
                ]);

                return new KhaltiVerificationResult($payment, null);
            }

            $payment->status = $newStatus;
            $payment->save();

            if ($newStatus !== Payment::STATUS_PENDING || $status !== self::STATUS_INITIATED) {
                Log::warning('Khalti payment not completed.', [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                    'lookup_status' => $status,
                ]);
            }

            return new KhaltiVerificationResult($payment, $status);
        });
    }

    /**
     * Re-check every open Khalti payment of a booking (used before an
     * unpaid booking is expired). Returns false when the booking must NOT
     * be expired yet: it was just paid, Khalti still has money in flight
     * (Pending), or Khalti could not be asked.
     */
    public function mayExpire(Booking $booking, SettingsService $settings): bool
    {
        $mayExpire = true;

        $booking->payments()
            ->where('provider', 'khalti')
            ->where('status', Payment::STATUS_PENDING)
            ->each(function (Payment $payment) use ($settings, &$mayExpire) {
                $result = $this->reconcile($payment);
                $this->notifyIfJustConfirmed($result, $settings);

                if ($result->isCompleted() || $result->isAwaitingKhalti() || $result->isIndeterminate()) {
                    $mayExpire = false;
                }
            });

        return $mayExpire;
    }

    /**
     * For confirmations that happen with no customer request in progress
     * (scheduled reconciliation): send the normal booking-confirmed emails,
     * once — only for the call that actually completed the payment.
     */
    public function notifyIfJustConfirmed(KhaltiVerificationResult $result, SettingsService $settings): void
    {
        if (! $result->justCompleted) {
            return;
        }

        $booking = $result->payment->booking()->with('room')->first();

        if ($booking?->status === Booking::STATUS_CONFIRMED) {
            $this->bookingService->sendNotifications($booking, $settings->current()->site_email);
        }
    }

    /**
     * Server-to-server status check — the only source of truth for whether
     * a Khalti payment succeeded. https://docs.khalti.com/khalti-epayment/#lookup-api
     *
     * Returns Khalti's response body when it states a status (Khalti
     * answers some final states such as Expired with a 4xx status code but
     * a normal body), or null when no trustworthy answer was received.
     */
    protected function lookup(?string $pidx): ?array
    {
        if (! filled($pidx) || ! filled($this->secretKey)) {
            Log::error('Khalti lookup not possible (missing pidx or KHALTI_SECRET_KEY).', ['pidx' => $pidx]);

            return null;
        }

        try {
            $response = Http::timeout(config('khalti.timeout', 15))->withHeaders([
                'Authorization' => "Key {$this->secretKey}",
            ])->post($this->baseUrl().'epayment/lookup/', ['pidx' => $pidx]);
        } catch (ConnectionException $e) {
            Log::warning('Khalti lookup request could not connect.', ['pidx' => $pidx]);

            return null;
        }

        $body = $response->json();

        if (! is_array($body) || ! isset($body['status']) || ! is_string($body['status']) || $response->serverError()) {
            Log::warning('Khalti lookup returned no usable status.', [
                'pidx' => $pidx,
                'http_status' => $response->status(),
            ]);

            return null;
        }

        return $body;
    }

    /**
     * Keep the original initiate response next to the latest lookup.
     */
    protected function withLookup(?array $gatewayResponse, array $lookup): array
    {
        $gatewayResponse ??= [];

        // Records created before gateway_response was split into
        // initiate/lookup held the bare initiate response.
        if (! array_key_exists('initiate', $gatewayResponse) && ! array_key_exists('lookup', $gatewayResponse)) {
            $gatewayResponse = $gatewayResponse ? ['initiate' => $gatewayResponse] : [];
        }

        $gatewayResponse['lookup'] = $lookup;

        return $gatewayResponse;
    }

    /**
     * Optional customer_info for Khalti's checkout. The phone is only sent
     * when it is a valid Nepali mobile in Khalti's 10-digit format — any
     * other value could make Khalti reject the whole payment request.
     */
    protected function customerInfo(Booking $booking): array
    {
        $info = [
            'name' => $booking->customer_name,
            'email' => $booking->email,
        ];

        $phone = $this->phones->toE164($booking->phone);

        if ($phone && $this->phones->regionFor($phone) === 'NP' && $this->phones->isSmsCapable($phone)) {
            $info['phone'] = $this->phones->nationalNumber($phone);
        }

        return $info;
    }
}
