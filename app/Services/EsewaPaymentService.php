<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\GatewayEnvironmentGuard;
use App\Services\Payments\PaymentCheckResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-side integration with eSewa's ePay v2 API
 * (https://developer.esewa.com.np/pages/Epay-V2).
 *
 * Flow: initiate() creates a unique pending Payment (transaction_uuid) and
 * the signed form fields the browser posts to eSewa. Whether the payment
 * succeeded is ONLY ever decided by reconcile(), which asks eSewa's
 * server-to-server status API. reconcile() is called when the customer
 * comes back (success or failure URL) and, because customers often do not
 * come back (closed tab, app switch, lost connection), by the scheduled
 * `payments:reconcile` command and before an unpaid booking is expired.
 *
 * eSewa status → our handling:
 *   COMPLETE (exact amount)       → completed, booking confirmed (or flagged for staff)
 *   PENDING / AMBIGUOUS           → stays pending; money may be in flight
 *   NOT_FOUND                     → stays pending while the attempt is young
 *                                   (the guest may still be on eSewa's page),
 *                                   failed once older than abandon_after_minutes
 *   CANCELED                      → cancelled
 *   FULL_REFUND / PARTIAL_REFUND  → failed (+ flagged for staff)
 *   no usable answer (timeout,
 *   5xx, unreadable)              → unchanged; retried on the next run
 *
 * Every transition happens under a row lock and a completed payment is
 * never processed twice, so any number of callbacks and reconciliation
 * runs can overlap safely.
 */
class EsewaPaymentService implements PaymentGatewayInterface
{
    public const STATUS_COMPLETE = 'COMPLETE';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';

    public const STATUS_NOT_FOUND = 'NOT_FOUND';

    public const STATUS_CANCELED = 'CANCELED';

    public const STATUS_FULL_REFUND = 'FULL_REFUND';

    public const STATUS_PARTIAL_REFUND = 'PARTIAL_REFUND';

    /** Kept for backwards compatibility; see GatewayEnvironmentGuard. */
    public const PUBLIC_SANDBOX_SECRET = GatewayEnvironmentGuard::ESEWA_PUBLIC_SECRET;

    /** At most this many unpaid attempts may be open for one booking. */
    public const MAX_OPEN_ATTEMPTS = 5;

    protected string $mode;

    protected ?string $productCode;

    protected ?string $secretKey;

    public function __construct(
        protected BookingService $bookingService,
        protected GatewayEnvironmentGuard $guard,
    ) {
        $this->mode = (string) config('esewa.mode', 'sandbox');
        $this->productCode = config('esewa.product_code');
        $this->secretKey = config('esewa.secret_key');
    }

    public function isAvailable(): bool
    {
        return (bool) config('payments.methods.esewa.enabled')
            && filled($this->productCode)
            && filled($this->secretKey)
            && $this->guard->isSafe('esewa');
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
     * Pending eSewa attempts of a booking, oldest first.
     *
     * @return Collection<int, Payment>
     */
    public function openPayments(Booking $booking)
    {
        return $booking->payments()
            ->where('provider', 'esewa')
            ->where('status', Payment::STATUS_PENDING)
            ->orderBy('id')
            ->get();
    }

    /**
     * Before a new attempt is started, settle the booking's earlier ones
     * with eSewa. Returns the result that must stop a new attempt (paid, or
     * money possibly in flight), or null when a new attempt is safe.
     */
    public function blockingAttempt(Booking $booking): ?PaymentCheckResult
    {
        foreach ($this->openPayments($booking) as $open) {
            $result = $this->reconcile($open);

            if ($result->isCompleted() || $result->mayHaveMoneyInFlight()) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Create a new, unique pending Payment for this booking and return the
     * exact, signed form fields the browser should POST to eSewa.
     *
     * The amount is the booking's server-calculated amount due now — never
     * request input. Earlier unpaid attempts stay pending (and keep being
     * reconciled) in case the guest still completes one in another tab; a
     * second completed payment is flagged for a refund, never lost.
     *
     * @throws \RuntimeException if the booking is already paid or has too many open attempts
     */
    public function initiate(Booking $booking): array
    {
        if ($booking->isPaid()) {
            throw new \RuntimeException('This booking has already been paid for.');
        }

        if ($this->openPayments($booking)->count() >= self::MAX_OPEN_ATTEMPTS) {
            throw new \RuntimeException('Too many unfinished eSewa attempts for this booking.');
        }

        $transactionUuid = $booking->confirmation_code.'-'.Str::upper(Str::random(10));

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'esewa',
            'transaction_uuid' => $transactionUuid,
            'amount' => $booking->amountDueNow(),
            'currency' => 'NPR',
            'status' => Payment::STATUS_PENDING,
        ]);

        $amount = $this->formatAmount($payment->amount);

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
     * Identify the attempt the customer's return (success OR failure URL)
     * is about. eSewa's base64 "data" is only used to find OUR payment row
     * by its high-entropy transaction_uuid; its claims are never trusted.
     *
     * @throws \RuntimeException if the payment cannot be identified
     */
    public function paymentFromReturn(?string $encodedData): Payment
    {
        if (! is_string($encodedData) || $encodedData === '' || strlen($encodedData) > 4096) {
            throw new \RuntimeException('No payment data was returned by eSewa.');
        }

        $decoded = json_decode((string) base64_decode($encodedData, true), true);

        if (! is_array($decoded) || ! is_string($decoded['transaction_uuid'] ?? null) || $decoded['transaction_uuid'] === '') {
            throw new \RuntimeException('The payment response from eSewa could not be read.');
        }

        $payment = Payment::where('provider', 'esewa')->where('transaction_uuid', $decoded['transaction_uuid'])->first();

        if (! $payment) {
            Log::warning('eSewa return referenced an unknown transaction_uuid.');

            throw new \RuntimeException('This payment could not be matched to a booking.');
        }

        if (! $this->verifySignature($decoded)) {
            // Harmless — the status API decides — but worth knowing about.
            Log::warning('eSewa return carried an invalid signature.', ['payment_id' => $payment->id]);
        }

        return $payment;
    }

    /**
     * The customer came back via the SUCCESS URL.
     *
     * @throws \RuntimeException if the payment cannot be identified
     */
    public function handleReturn(?string $encodedData): PaymentCheckResult
    {
        return $this->reconcile($this->paymentFromReturn($encodedData));
    }

    /**
     * The customer came back via the FAILURE URL. Still verified with
     * eSewa: only if eSewa has no completed (or in-flight) payment is the
     * attempt closed as cancelled. Returns null if the attempt can't be identified.
     */
    public function handleFailure(?string $encodedData): ?PaymentCheckResult
    {
        try {
            $payment = $this->paymentFromReturn($encodedData);
        } catch (\RuntimeException) {
            return null;
        }

        $result = $this->reconcile($payment);

        if ($result->outcome !== PaymentCheckResult::UNPAID) {
            return $result;
        }

        return DB::transaction(function () use ($payment, $result) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $payment->isPending()) {
                return new PaymentCheckResult($payment, $result->outcome, $result->gatewayStatus);
            }

            $payment->status = Payment::STATUS_CANCELLED;
            $payment->save();

            return new PaymentCheckResult($payment, PaymentCheckResult::CANCELLED, $result->gatewayStatus);
        });
    }

    /**
     * Ask eSewa for the real status of one attempt and apply it. Idempotent
     * and safe from any number of concurrent requests/jobs: the payment row
     * is locked and a completed payment is never re-processed.
     */
    public function reconcile(Payment $payment): PaymentCheckResult
    {
        try {
            return $this->reconcileNow($payment);
        } catch (ValidationException) {
            // The booking's rooms stayed locked by other requests (see
            // BookingService::inventoryTransaction()); everything was rolled
            // back, so nothing is decided — the next run settles it.
            return new PaymentCheckResult($payment->fresh(), PaymentCheckResult::UNKNOWN);
        }
    }

    protected function reconcileNow(Payment $payment): PaymentCheckResult
    {
        if ($payment->isCompleted()) {
            return new PaymentCheckResult($payment, PaymentCheckResult::COMPLETED, self::STATUS_COMPLETE);
        }

        // A production site misconfigured for the sandbox never confirms anything.
        if (! $this->guard->isSafe('esewa')) {
            return new PaymentCheckResult($payment, PaymentCheckResult::UNKNOWN);
        }

        // Network call outside the DB transaction, so no lock is held while waiting on eSewa.
        $status = $this->checkStatus($payment);

        return DB::transaction(function () use ($payment, $status) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->isCompleted()) {
                return new PaymentCheckResult($payment, PaymentCheckResult::COMPLETED, self::STATUS_COMPLETE);
            }

            $payment->last_checked_at = now();
            $payment->check_attempts = $payment->check_attempts + 1;

            if ($status === null) {
                $payment->save();

                return new PaymentCheckResult($payment, PaymentCheckResult::UNKNOWN);
            }

            $gatewayStatus = is_string($status['status'] ?? null) ? strtoupper($status['status']) : null;
            $payment->gateway_response = array_merge((array) $payment->gateway_response, ['status_check' => $status]);

            if ($gatewayStatus === self::STATUS_COMPLETE) {
                return $this->complete($payment, $status);
            }

            [$newStatus, $outcome] = match ($gatewayStatus) {
                self::STATUS_PENDING, self::STATUS_AMBIGUOUS => [Payment::STATUS_PENDING, PaymentCheckResult::AWAITING],
                self::STATUS_NOT_FOUND => $this->isAbandoned($payment)
                    ? [Payment::STATUS_FAILED, PaymentCheckResult::FAILED]
                    : [Payment::STATUS_PENDING, PaymentCheckResult::UNPAID],
                self::STATUS_CANCELED => [Payment::STATUS_CANCELLED, PaymentCheckResult::CANCELLED],
                self::STATUS_FULL_REFUND, self::STATUS_PARTIAL_REFUND => [Payment::STATUS_FAILED, PaymentCheckResult::FAILED],
                default => [Payment::STATUS_PENDING, PaymentCheckResult::UNKNOWN],
            };

            if ($outcome === PaymentCheckResult::UNKNOWN) {
                Log::warning('eSewa status check returned an unrecognised status — leaving payment pending.', [
                    'payment_id' => $payment->id,
                    'gateway_status' => $gatewayStatus,
                ]);
            }

            $payment->status = $newStatus;
            $payment->save();

            if (in_array($gatewayStatus, [self::STATUS_FULL_REFUND, self::STATUS_PARTIAL_REFUND], true)) {
                $this->bookingService->flagPaymentForReview($payment, "eSewa reports this payment as {$gatewayStatus}. Check the eSewa merchant portal and the booking.");
            }

            return new PaymentCheckResult($payment, $outcome, $gatewayStatus);
        });
    }

    /**
     * eSewa said COMPLETE: accept it only for exactly this transaction,
     * product and amount. Runs inside reconcile()'s transaction, with the
     * payment row locked.
     */
    protected function complete(Payment $payment, array $status): PaymentCheckResult
    {
        $gatewayAmount = is_numeric($status['total_amount'] ?? null) ? (float) $status['total_amount'] : null;

        $matches = ($status['transaction_uuid'] ?? null) === $payment->transaction_uuid
            && ($status['product_code'] ?? null) === $this->productCode
            && $gatewayAmount !== null
            && abs($gatewayAmount - (float) $payment->amount) < 0.01;

        if (! $matches) {
            $payment->status = Payment::STATUS_FAILED;
            $payment->save();

            Log::error('eSewa reported COMPLETE for a different transaction/product/amount — not confirming.', [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
            ]);

            $this->bookingService->flagPaymentForReview($payment, 'eSewa reported a completed payment that does not match what was charged (amount/product/reference). The booking was NOT confirmed — check the eSewa merchant portal.');

            return new PaymentCheckResult($payment, PaymentCheckResult::FAILED, self::STATUS_COMPLETE);
        }

        $payment->status = Payment::STATUS_COMPLETED;
        $payment->reference_id = is_string($status['ref_id'] ?? null) ? $status['ref_id'] : $payment->reference_id;
        $payment->paid_at = now();
        $payment->save();

        $this->bookingService->confirmAfterVerifiedPayment($payment);

        Log::info('eSewa payment verified and completed.', ['payment_id' => $payment->id, 'booking_id' => $payment->booking_id]);

        return new PaymentCheckResult($payment, PaymentCheckResult::COMPLETED, self::STATUS_COMPLETE, justCompleted: true);
    }

    /**
     * An attempt eSewa has never heard of, old enough that the guest can no
     * longer be on eSewa's page.
     */
    protected function isAbandoned(Payment $payment): bool
    {
        return $payment->created_at->lte(now()->subMinutes((int) config('esewa.abandon_after_minutes', 180)));
    }

    /**
     * eSewa's status API, server-to-server. Returns its JSON body, or null
     * when no trustworthy answer was received (timeout, connection error,
     * 5xx, unreadable body) — never a guessed status.
     */
    protected function checkStatus(Payment $payment): ?array
    {
        try {
            $response = Http::timeout(15)->get($this->statusUrl(), [
                'product_code' => $this->productCode,
                'total_amount' => $this->formatAmount($payment->amount),
                'transaction_uuid' => $payment->transaction_uuid,
            ]);
        } catch (ConnectionException) {
            Log::warning('eSewa status check could not connect.', ['payment_id' => $payment->id]);

            return null;
        }

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['status'] ?? null)) {
            Log::warning('eSewa status check gave no usable answer.', [
                'payment_id' => $payment->id,
                'http_status' => $response->status(),
            ]);

            return null;
        }

        // eSewa answers some definitive states (e.g. NOT_FOUND) with a 4xx
        // code but a normal body; a 5xx is an outage, whatever the body says.
        if ($response->serverError()) {
            return null;
        }

        return $body;
    }

    protected function verifySignature(array $decoded): bool
    {
        if (! filled($this->secretKey) || ! is_string($decoded['signed_field_names'] ?? null) || ! is_string($decoded['signature'] ?? null)) {
            return false;
        }

        $fields = [];
        foreach (explode(',', $decoded['signed_field_names']) as $name) {
            $fields[$name] = $decoded[$name] ?? '';
        }

        return hash_equals($this->sign($fields), $decoded['signature']);
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

        return base64_encode(hash_hmac('sha256', $message, (string) $this->secretKey, true));
    }

    protected function formatAmount(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
