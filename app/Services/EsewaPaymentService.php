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
 * Server-side integration with eSewa's ePay v2 API.
 *
 * Flow: initiate() creates a Payment row and the signed form fields the
 * browser posts to eSewa. eSewa redirects the customer back to our
 * success/failure routes; handleReturn() re-verifies that redirect against
 * eSewa's own server-to-server status API before trusting it — the redirect
 * itself is never treated as proof of payment.
 */
class EsewaPaymentService implements PaymentGatewayInterface
{
    protected string $mode;

    protected string $productCode;

    protected string $secretKey;

    public function __construct(protected BookingService $bookingService)
    {
        $this->mode = config('esewa.mode', 'sandbox');
        $this->productCode = config('esewa.product_code');
        $this->secretKey = config('esewa.secret_key');
    }

    public function isAvailable(): bool
    {
        return (bool) config('payments.methods.esewa.enabled');
    }

    protected function formUrl(): string
    {
        return config("esewa.urls.{$this->mode}.form");
    }

    protected function statusUrl(): string
    {
        return config("esewa.urls.{$this->mode}.status");
    }

    /**
     * Create (or reuse) a pending Payment for this booking and return the
     * exact, signed form fields the browser should POST to eSewa.
     *
     * The amount is always read from the booking's server-calculated
     * total_price — never from request input — so it cannot be tampered
     * with from the client.
     */
    public function initiate(Booking $booking): array
    {
        if ($booking->isPaid()) {
            throw new \RuntimeException('This booking has already been paid for.');
        }

        $transactionUuid = $booking->confirmation_code.'-'.Str::upper(Str::random(8));

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'esewa',
            'transaction_uuid' => $transactionUuid,
            'amount' => $booking->total_price,
            'currency' => 'NPR',
            'status' => Payment::STATUS_PENDING,
        ]);

        $amount = number_format((float) $booking->total_price, 2, '.', '');

        $fields = [
            'amount' => $amount,
            'tax_amount' => '0',
            'total_amount' => $amount,
            'transaction_uuid' => $transactionUuid,
            'product_code' => $this->productCode,
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'success_url' => route('payment.esewa.success'),
            'failure_url' => route('payment.esewa.failure'),
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
        ];

        $fields['signature'] = $this->sign([
            'total_amount' => $amount,
            'transaction_uuid' => $transactionUuid,
            'product_code' => $this->productCode,
        ]);

        return [
            'payment' => $payment,
            'action_url' => $this->formUrl(),
            'fields' => $fields,
        ];
    }

    /**
     * Process the customer's return from eSewa (both the success and
     * failure routes land here with whatever query data eSewa provided).
     * Returns the Payment row after processing.
     *
     * @throws \RuntimeException if the payment cannot be identified/verified
     */
    public function handleReturn(?string $encodedData): Payment
    {
        if (! $encodedData) {
            throw new \RuntimeException('No payment data was returned by eSewa.');
        }

        $decoded = json_decode(base64_decode($encodedData), true);

        if (! is_array($decoded) || empty($decoded['transaction_uuid'])) {
            throw new \RuntimeException('The payment response from eSewa could not be read.');
        }

        $payment = Payment::where('transaction_uuid', $decoded['transaction_uuid'])->first();

        if (! $payment) {
            Log::warning('eSewa return referenced an unknown transaction_uuid.', ['data' => $decoded]);
            throw new \RuntimeException('This payment could not be matched to a booking.');
        }

        // Idempotent: if we already processed this transaction as completed,
        // just return it as-is rather than re-verifying/re-confirming.
        if ($payment->isCompleted()) {
            return $payment;
        }

        // The redirect payload is signed by eSewa; check that signature as a
        // first sanity check, but it is not the authoritative source of truth.
        $redirectSignatureValid = $this->verifySignature($decoded);

        // Authoritative check: ask eSewa directly, server-to-server, whether
        // this transaction actually completed. Never trust the browser
        // redirect alone for something as sensitive as payment confirmation.
        $statusResponse = $this->checkStatus($payment);

        return DB::transaction(function () use ($payment, $decoded, $redirectSignatureValid, $statusResponse) {
            // Re-lock and re-read the payment inside the transaction in case
            // a duplicate/concurrent callback is being processed simultaneously.
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($payment->isCompleted()) {
                return $payment;
            }

            $gatewayStatus = $statusResponse['status'] ?? null;
            $gatewayAmount = isset($statusResponse['total_amount']) ? (float) $statusResponse['total_amount'] : null;
            $expectedAmount = (float) $payment->amount;

            $verified = $redirectSignatureValid
                && $gatewayStatus === 'COMPLETE'
                && $gatewayAmount !== null
                && abs($gatewayAmount - $expectedAmount) < 0.01
                && ($statusResponse['transaction_uuid'] ?? null) === $payment->transaction_uuid
                && ($statusResponse['product_code'] ?? null) === $this->productCode;

            $payment->gateway_response = $statusResponse + ['redirect_data' => $decoded];

            if (! $verified) {
                $payment->status = Payment::STATUS_FAILED;
                $payment->save();

                Log::warning('eSewa payment verification failed.', [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                    'gateway_status' => $gatewayStatus,
                    'signature_valid' => $redirectSignatureValid,
                ]);

                return $payment;
            }

            $payment->status = Payment::STATUS_COMPLETED;
            $payment->reference_id = $statusResponse['ref_id'] ?? ($decoded['transaction_code'] ?? null);
            $payment->paid_at = now();
            $payment->save();

            $this->bookingService->confirmAfterVerifiedPayment($payment);

            return $payment;
        });
    }

    /**
     * Mark a payment as failed when the customer lands on the failure
     * route (payment declined/cancelled on eSewa's side).
     */
    public function markFailed(?string $encodedData): ?Payment
    {
        if (! $encodedData) {
            return null;
        }

        $decoded = json_decode(base64_decode($encodedData), true);

        if (! is_array($decoded) || empty($decoded['transaction_uuid'])) {
            return null;
        }

        $payment = Payment::where('transaction_uuid', $decoded['transaction_uuid'])->first();

        if ($payment && ! $payment->isCompleted()) {
            $payment->status = Payment::STATUS_FAILED;
            $payment->gateway_response = $decoded;
            $payment->save();
        }

        return $payment;
    }

    protected function checkStatus(Payment $payment): array
    {
        $response = Http::timeout(15)->get($this->statusUrl(), [
            'product_code' => $this->productCode,
            'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'transaction_uuid' => $payment->transaction_uuid,
        ]);

        if (! $response->successful()) {
            Log::warning('eSewa status check request failed.', [
                'payment_id' => $payment->id,
                'http_status' => $response->status(),
            ]);

            return ['status' => 'NOT_FOUND'];
        }

        return $response->json() ?? ['status' => 'NOT_FOUND'];
    }

    protected function verifySignature(array $decoded): bool
    {
        $signedFieldNames = explode(',', $decoded['signed_field_names'] ?? '');

        if (empty($signedFieldNames) || empty($decoded['signature'])) {
            return false;
        }

        $fields = [];
        foreach ($signedFieldNames as $name) {
            $fields[$name] = $decoded[$name] ?? '';
        }

        $expected = $this->sign($fields);

        return hash_equals($expected, $decoded['signature']);
    }

    /**
     * eSewa's HMAC-SHA256 signature: "field1=value1,field2=value2,..." in
     * the exact order given, base64-encoded.
     */
    protected function sign(array $orderedFields): string
    {
        $message = collect($orderedFields)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(',');

        return base64_encode(hash_hmac('sha256', $message, $this->secretKey, true));
    }
}
