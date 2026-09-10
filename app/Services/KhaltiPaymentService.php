<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Booking;
use App\Models\Payment;
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
 * trusted directly. handleReturn() re-verifies via Khalti's own
 * /epayment/lookup/ endpoint (server-to-server) before ever confirming a
 * booking; only a lookup response of status "Completed" with a matching
 * amount is treated as paid, exactly as Khalti's own docs require ("Only
 * the status with Completed must be treated as success").
 *
 * NOTE: this service is fully implemented against Khalti's documented API
 * shape, but has not yet been exercised against a live sandbox transaction
 * — Khalti's sandbox requires a per-merchant secret key from
 * test-admin.khalti.com (email-verified signup), which has not been
 * completed for this project yet. It stays disabled (see
 * config/payments.php's KHALTI_ENABLED) until a real key is configured.
 */
class KhaltiPaymentService implements PaymentGatewayInterface
{
    protected string $mode;

    protected ?string $secretKey;

    public function __construct(protected BookingService $bookingService)
    {
        $this->mode = config('khalti.mode', 'sandbox');
        $this->secretKey = config('khalti.secret_key');
    }

    public function isAvailable(): bool
    {
        return (bool) config('payments.methods.khalti.enabled') && filled($this->secretKey);
    }

    protected function baseUrl(): string
    {
        return config("khalti.urls.{$this->mode}.base");
    }

    /**
     * Create a pending Payment and ask Khalti to start a payment session
     * for it. The amount is always read from the booking's own
     * server-calculated total_price — never from request input.
     *
     * @return array{payment: Payment, payment_url: string}
     *
     * @throws \RuntimeException if Khalti is not configured, or rejects the request
     */
    public function initiate(Booking $booking): array
    {
        if (! filled($this->secretKey)) {
            throw new \RuntimeException('Khalti is not configured (missing KHALTI_SECRET_KEY).');
        }

        $purchaseOrderId = $booking->confirmation_code.'-'.Str::upper(Str::random(8));
        $amountPaisa = (int) round(((float) $booking->total_price) * 100);

        $response = Http::withHeaders([
            'Authorization' => "Key {$this->secretKey}",
        ])->post($this->baseUrl().'epayment/initiate/', [
            'return_url' => route('payment.khalti.return'),
            'website_url' => config('app.url'),
            'amount' => $amountPaisa,
            'purchase_order_id' => $purchaseOrderId,
            'purchase_order_name' => "Room booking {$booking->confirmation_code}",
            'customer_info' => [
                'name' => $booking->customer_name,
                'email' => $booking->email,
                'phone' => $booking->phone,
            ],
        ]);

        if (! $response->successful() || empty($response->json('pidx'))) {
            Log::error('Khalti payment initiation failed.', [
                'booking_id' => $booking->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new \RuntimeException('Khalti did not accept the payment request.');
        }

        $pidx = $response->json('pidx');

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'khalti',
            'transaction_uuid' => $purchaseOrderId,
            'reference_id' => $pidx,
            'amount' => $booking->total_price,
            'currency' => 'NPR',
            'status' => Payment::STATUS_PENDING,
            'gateway_response' => $response->json(),
        ]);

        return [
            'payment' => $payment,
            'payment_url' => $response->json('payment_url'),
        ];
    }

    /**
     * Process the customer's return from Khalti. Always re-verifies against
     * Khalti's own lookup API — the query string alone is never trusted.
     *
     * @throws \RuntimeException if the payment cannot be identified
     */
    public function handleReturn(?string $pidx): Payment
    {
        if (! $pidx) {
            throw new \RuntimeException('No payment reference was returned by Khalti.');
        }

        $payment = Payment::where('provider', 'khalti')->where('reference_id', $pidx)->first();

        if (! $payment) {
            Log::warning('Khalti return referenced an unknown pidx.', ['pidx' => $pidx]);

            throw new \RuntimeException('This payment could not be matched to a booking.');
        }

        // Idempotent: if already processed as completed, return as-is.
        if ($payment->isCompleted()) {
            return $payment;
        }

        $lookup = $this->lookup($pidx);

        return DB::transaction(function () use ($payment, $lookup) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($payment->isCompleted()) {
                return $payment;
            }

            $expectedAmountPaisa = (int) round(((float) $payment->amount) * 100);
            $gatewayAmountPaisa = isset($lookup['total_amount']) ? (int) $lookup['total_amount'] : null;

            // Khalti's docs: "Only the status with Completed must be treated
            // as success" — Pending/Refunded/Expired/User canceled are all failures here.
            $verified = ($lookup['status'] ?? null) === 'Completed'
                && $gatewayAmountPaisa !== null
                && $gatewayAmountPaisa === $expectedAmountPaisa;

            $payment->gateway_response = $lookup;

            if (! $verified) {
                $payment->status = Payment::STATUS_FAILED;
                $payment->save();

                Log::warning('Khalti payment verification failed.', [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                    'lookup_status' => $lookup['status'] ?? null,
                ]);

                return $payment;
            }

            $payment->status = Payment::STATUS_COMPLETED;
            $payment->paid_at = now();
            $payment->save();

            $this->bookingService->confirmAfterVerifiedPayment($payment);

            return $payment;
        });
    }

    /**
     * Server-to-server transaction status check — the only source of truth
     * for whether a Khalti payment actually succeeded.
     * https://docs.khalti.com/khalti-epayment/#lookup-api
     */
    protected function lookup(string $pidx): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Key {$this->secretKey}",
        ])->post($this->baseUrl().'epayment/lookup/', ['pidx' => $pidx]);

        if (! $response->successful()) {
            Log::warning('Khalti lookup request failed.', [
                'pidx' => $pidx,
                'http_status' => $response->status(),
            ]);

            return ['status' => 'Not Found'];
        }

        return $response->json() ?? ['status' => 'Not Found'];
    }
}
