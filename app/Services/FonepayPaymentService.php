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
 * Server-side integration with Fonepay's merchant web-payment (redirect) API.
 * Source of truth: Fonepay's own published integration code —
 * github.com/fonepay/Fonepay-Java and its Spring Boot sample (see
 * config/fonepay.php and docs/PAYMENTS.md).
 *
 * Flow: initiate() creates a unique pending Payment and builds a signed
 * Fonepay URL (HMAC-SHA512 "DV" over the request fields) which the
 * customer's browser is redirected to. Fonepay redirects back to our single
 * return route with PRN/UID/BID/PS/RC/... in the query string — none of
 * which is trusted directly. Fonepay's server-to-server verification
 * endpoint (with the amount taken from OUR payment record) decides; only a
 * response of response_code "successful" is ever treated as paid.
 *
 * Reconciliation: Fonepay's published web-payment protocol can only verify
 * a payment with the trace id (UID) and bill id (BID) that Fonepay hands to
 * the customer's browser on return — there is no status-by-reference
 * lookup. So:
 *  - the UID/BID are stored BEFORE verifying, and a verification that
 *    times out is retried later by `payments:reconcile` (reconcile());
 *  - an attempt whose customer never came back cannot be asked about at
 *    all: before its booking is expired it is flagged for staff to check
 *    the Fonepay merchant portal (see PaymentReconciler::mayExpire()).
 *
 * NOTE: this service is implemented against Fonepay's published request/
 * verification protocol, but has not been exercised against a live Fonepay
 * sandbox transaction — that needs a dev merchant code + secret key issued
 * to this merchant by Fonepay. It stays hidden until credentials are set.
 */
class FonepayPaymentService implements PaymentGatewayInterface
{
    protected string $mode;

    protected ?string $merchantCode;

    protected ?string $secretKey;

    public function __construct(
        protected BookingService $bookingService,
        protected GatewayEnvironmentGuard $guard,
    ) {
        $this->mode = (string) config('fonepay.mode', 'sandbox');
        $this->merchantCode = config('fonepay.merchant_code');
        $this->secretKey = config('fonepay.secret_key');
    }

    public function isAvailable(): bool
    {
        return (bool) config('payments.methods.fonepay.enabled')
            && filled($this->merchantCode)
            && filled($this->secretKey)
            && $this->guard->isSafe('fonepay');
    }

    protected function baseUrl(): string
    {
        return rtrim(config("fonepay.urls.{$this->mode}.base"), '/');
    }

    /**
     * Pending Fonepay attempts of a booking, oldest first.
     *
     * @return Collection<int, Payment>
     */
    public function openPayments(Booking $booking)
    {
        return $booking->payments()
            ->where('provider', 'fonepay')
            ->where('status', Payment::STATUS_PENDING)
            ->orderBy('id')
            ->get();
    }

    /**
     * Before a new attempt is started: an earlier attempt that Fonepay
     * already handed back a trace id for (money may have moved) is verified
     * first. Returns the result that must stop a new attempt, or null.
     */
    public function blockingAttempt(Booking $booking): ?PaymentCheckResult
    {
        foreach ($this->openPayments($booking) as $open) {
            if (! $this->hasTraceIds($open)) {
                continue;
            }

            $result = $this->reconcile($open);

            if ($result->isCompleted() || $result->mayHaveMoneyInFlight()) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Create a new, unique pending Payment and build the signed Fonepay URL
     * the customer is redirected to. The amount is the booking's own
     * server-calculated amount due now — never request input.
     *
     * @return array{payment: Payment, payment_url: string}
     *
     * @throws \RuntimeException if Fonepay is not configured, or the booking is already paid
     */
    public function initiate(Booking $booking): array
    {
        if (! filled($this->merchantCode) || ! filled($this->secretKey)) {
            throw new \RuntimeException('Fonepay is not configured (missing FONEPAY_MERCHANT_CODE / FONEPAY_SECRET_KEY).');
        }

        if ($booking->isPaid()) {
            throw new \RuntimeException('This booking has already been paid for.');
        }

        // PRN must be 3-25 characters; NW2026ABCDEF + 8 random = 20. Kept
        // alphanumeric (no dashes) since Fonepay's own samples only use that.
        $prn = str_replace('-', '', $booking->confirmation_code).Str::upper(Str::random(8));

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'fonepay',
            'transaction_uuid' => $prn,
            'amount' => $booking->amountDueNow(),
            'currency' => 'NPR',
            'status' => Payment::STATUS_PENDING,
        ]);

        $fields = [
            'PID' => $this->merchantCode,
            'MD' => 'P',
            'AMT' => $this->formatAmount($payment->amount),
            'CRN' => (string) config('fonepay.currency', 'NPR'),
            'R1' => "Room booking {$booking->confirmation_code}",
            'R2' => 'N/A',
            'DT' => now('Asia/Kathmandu')->format('m/d/Y'),
            'PRN' => $prn,
            'RU' => route('payment.fonepay.return'),
        ];

        // DV is an HMAC-SHA512 over PID,MD,PRN,AMT,CRN,DT,R1,R2,RU — the
        // secret key itself is never part of the URL.
        $fields['DV'] = $this->sign([
            $fields['PID'], $fields['MD'], $fields['PRN'], $fields['AMT'], $fields['CRN'],
            $fields['DT'], $fields['R1'], $fields['R2'], $fields['RU'],
        ]);

        return [
            'payment' => $payment,
            'payment_url' => $this->baseUrl().'/api/merchantRequest?'.http_build_query($fields),
        ];
    }

    /**
     * Process the customer's return from Fonepay. The query string is only
     * used to find OUR payment (by PRN) and to obtain Fonepay's trace/bill
     * ids, which are stored and then verified with Fonepay.
     *
     * @param  array<string, mixed>  $params  the return URL's query string
     *
     * @throws \RuntimeException if the payment cannot be identified
     */
    public function handleReturn(array $params): PaymentCheckResult
    {
        $prn = $params['PRN'] ?? null;

        if (! is_string($prn) || $prn === '' || strlen($prn) > 64) {
            throw new \RuntimeException('No payment reference was returned by Fonepay.');
        }

        $payment = Payment::where('provider', 'fonepay')->where('transaction_uuid', $prn)->first();

        if (! $payment) {
            Log::warning('Fonepay return referenced an unknown PRN.', ['prn' => Str::limit($prn, 64, '')]);

            throw new \RuntimeException('This payment could not be matched to a booking.');
        }

        // Idempotent: a duplicate/replayed callback for a completed payment is returned as-is.
        if ($payment->isCompleted()) {
            return new PaymentCheckResult($payment, PaymentCheckResult::COMPLETED);
        }

        $uid = $this->stringParam($params, 'UID');
        // The official sample reads this as BID; older Fonepay callbacks
        // document it as BC. Either way it is a Fonepay-issued value that
        // is only ever passed back to Fonepay, which validates it.
        $billId = $this->stringParam($params, 'BID') ?? $this->stringParam($params, 'BC');

        // No Fonepay trace id (or bill id) means no Fonepay transaction ever
        // happened — the customer backed out. There is nothing to verify.
        if ($uid === null || $billId === null) {
            $payment = $this->markNotPaid($payment, $params);

            return new PaymentCheckResult($payment, $payment->status === Payment::STATUS_CANCELLED ? PaymentCheckResult::CANCELLED : PaymentCheckResult::FAILED);
        }

        // Keep the trace ids first, so a verification that times out now
        // can still be retried later by reconciliation.
        DB::transaction(function () use ($payment, $uid, $billId, $params) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            // Also on a cancelled/failed attempt: Fonepay's word is final, so
            // a payment it later confirms is still settled (and if its booking
            // moved on meanwhile, flagged for staff).
            if (! $locked->isCompleted()) {
                $locked->gateway_response = array_merge((array) $locked->gateway_response, [
                    'trace' => ['uid' => $uid, 'bid' => $billId],
                    'return' => $this->safeReturnParams($params),
                ]);
                $locked->save();
            }
        });

        return $this->reconcile($payment->fresh());
    }

    /**
     * Verify one attempt with Fonepay using its stored trace ids and apply
     * the answer. Idempotent and safe under concurrency (row lock; a
     * completed payment is never re-processed). An attempt without trace
     * ids cannot be asked about: UNKNOWN.
     */
    public function reconcile(Payment $payment): PaymentCheckResult
    {
        try {
            return $this->reconcileNow($payment);
        } catch (ValidationException) {
            // Rooms stayed locked by other requests; everything was rolled
            // back, so nothing is decided — the next run settles it.
            return new PaymentCheckResult($payment->fresh(), PaymentCheckResult::UNKNOWN);
        }
    }

    protected function reconcileNow(Payment $payment): PaymentCheckResult
    {
        if ($payment->isCompleted()) {
            return new PaymentCheckResult($payment, PaymentCheckResult::COMPLETED);
        }

        if (! $this->hasTraceIds($payment) || ! $this->guard->isSafe('fonepay')) {
            return new PaymentCheckResult($payment, PaymentCheckResult::UNKNOWN);
        }

        $uid = $payment->gateway_response['trace']['uid'];
        $billId = $payment->gateway_response['trace']['bid'];

        // A trace id can only ever settle one payment.
        $uidAlreadyUsed = Payment::where('provider', 'fonepay')
            ->where('reference_id', $uid)
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereKeyNot($payment->id)
            ->exists();

        if ($uidAlreadyUsed) {
            Log::warning('Fonepay return reused a trace id already settled on another payment.', ['payment_id' => $payment->id]);

            $payment = $this->markNotPaid($payment, (array) ($payment->gateway_response['return'] ?? []), forceFailed: true);

            return new PaymentCheckResult($payment, PaymentCheckResult::FAILED);
        }

        // Network call outside the DB transaction.
        $verification = $this->verify($payment, $uid, $billId);

        return DB::transaction(function () use ($payment, $verification, $uid) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->isCompleted()) {
                return new PaymentCheckResult($payment, PaymentCheckResult::COMPLETED);
            }

            $payment->last_checked_at = now();
            $payment->check_attempts = $payment->check_attempts + 1;

            if ($verification === null) {
                // Fonepay unreachable/unreadable — decide nothing; retried later.
                $payment->save();

                return new PaymentCheckResult($payment, PaymentCheckResult::UNKNOWN);
            }

            $expected = (float) $payment->amount;
            $paid = $verification['txnAmount'] ?? null;

            // Fonepay only ever confirms the amount we sent it (AMT is part
            // of the signed verification request). As an extra guard, if it
            // reports what the customer actually paid, that must not be less
            // than the booking total (it may legitimately include a fee).
            $verified = $this->isPaidResponse($verification)
                && (! isset($verification['uniqueId']) || $verification['uniqueId'] === '' || $verification['uniqueId'] === $uid)
                && (! is_numeric($paid) || (float) $paid + 0.005 >= $expected);

            $payment->gateway_response = array_merge((array) $payment->gateway_response, ['verification' => $verification]);

            if (! $verified) {
                $payment->status = Payment::STATUS_FAILED;
                $payment->save();

                Log::warning('Fonepay payment verification failed.', [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                    'response_code' => $verification['response_code'] ?? $verification['responseCode'] ?? null,
                    'is_success' => $verification['isSuccess'] ?? null,
                ]);

                return new PaymentCheckResult($payment, PaymentCheckResult::FAILED);
            }

            $payment->status = Payment::STATUS_COMPLETED;
            $payment->reference_id = $uid;
            $payment->paid_at = now();
            $payment->save();

            $this->bookingService->confirmAfterVerifiedPayment($payment);

            return new PaymentCheckResult($payment, PaymentCheckResult::COMPLETED, 'successful', justCompleted: true);
        });
    }

    /** Whether Fonepay handed this attempt's trace/bill ids back to us. */
    public function hasTraceIds(Payment $payment): bool
    {
        return filled($payment->gateway_response['trace']['uid'] ?? null)
            && filled($payment->gateway_response['trace']['bid'] ?? null);
    }

    /**
     * Server-to-server transaction check — the only source of truth for
     * whether a Fonepay payment actually succeeded. The AMT sent is the
     * amount from our own payment record, so a tampered browser amount can
     * never be "verified".
     *
     * @return array<string, string>|null Fonepay's <response> fields, or null
     *                                    if the request failed / was unreadable
     */
    protected function verify(Payment $payment, string $uid, string $billId): ?array
    {
        $amount = $this->formatAmount($payment->amount);

        // DV is an HMAC-SHA512 over PID,AMT,PRN,BID,UID.
        $query = [
            'PRN' => $payment->transaction_uuid,
            'PID' => $this->merchantCode,
            'BID' => $billId,
            'AMT' => $amount,
            'UID' => $uid,
            'DV' => $this->sign([$this->merchantCode, $amount, $payment->transaction_uuid, $billId, $uid]),
        ];

        try {
            $response = Http::timeout(15)->get($this->baseUrl().'/api/merchantRequest/verificationMerchant', $query);
        } catch (ConnectionException $e) {
            Log::warning('Fonepay verification request could not connect.', ['payment_id' => $payment->id]);

            return null;
        }

        $parsed = $this->parseVerificationResponse($response->body());

        if (! $response->successful()) {
            // Fonepay's dev server answers a definitive "not paid" with a 4xx
            // JSON body (e.g. 409 {"message":"No data found for verification",
            // "isSuccess":false}). That is a real answer, not an outage.
            if ($response->clientError() && ($parsed['isSuccess'] ?? null) === 'false') {
                Log::warning('Fonepay verification reported the payment as not paid.', [
                    'payment_id' => $payment->id,
                    'http_status' => $response->status(),
                    'message' => $parsed['message'] ?? null,
                ]);

                return $parsed;
            }

            Log::warning('Fonepay verification request failed.', [
                'payment_id' => $payment->id,
                'http_status' => $response->status(),
            ]);

            return null;
        }

        if ($parsed === null) {
            Log::warning('Fonepay verification response was neither valid JSON nor XML.', ['payment_id' => $payment->id]);
        }

        return $parsed;
    }

    /**
     * Fonepay's published library documents an XML <response> reply; its live
     * dev server was observed replying with JSON ({"message":…,"isSuccess":…}).
     * Both are normalised into a flat string map so the rest of the service
     * doesn't care which it got. Anything unreadable returns null.
     *
     * @return array<string, string>|null
     */
    protected function parseVerificationResponse(string $body): ?array
    {
        $body = trim($body);

        if ($body === '') {
            return null;
        }

        if (str_starts_with($body, '{')) {
            $json = json_decode($body, true);

            if (! is_array($json)) {
                return null;
            }

            // Flatten one level of a nested "data" object without letting it
            // override top-level fields.
            $flat = $json + (is_array($json['data'] ?? null) ? $json['data'] : []);
            unset($flat['data']);

            $fields = [];
            foreach ($flat as $name => $value) {
                if (is_bool($value)) {
                    $fields[$name] = $value ? 'true' : 'false';
                } elseif (is_scalar($value)) {
                    $fields[$name] = trim((string) $value);
                }
            }

            return $fields === [] ? null : $fields;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return null;
        }

        $fields = [];
        foreach ($xml->children() as $name => $value) {
            $fields[(string) $name] = trim((string) $value);
        }

        return $fields === [] ? null : $fields;
    }

    /**
     * Whether a normalised verification response says the payment succeeded.
     * XML shape: response_code must be "successful" (Fonepay's own sample).
     * JSON shape: isSuccess must be true, and if a response code is present
     * it must not contradict that. Absent an explicit success, it is not paid.
     *
     * @param  array<string, string>  $v
     */
    protected function isPaidResponse(array $v): bool
    {
        $code = strtolower($v['response_code'] ?? $v['responseCode'] ?? '');

        if (isset($v['isSuccess'])) {
            if (strtolower($v['isSuccess']) !== 'true' || ($code !== '' && $code !== 'successful')) {
                return false;
            }
        } elseif ($code !== 'successful') {
            return false;
        }

        return strtolower($v['success'] ?? 'true') !== 'false';
    }

    /**
     * Record a return that carried no Fonepay transaction (customer backed
     * out) as cancelled — or failed if Fonepay said so explicitly. Never
     * touches a payment that has already been completed.
     */
    protected function markNotPaid(Payment $payment, array $params, bool $forceFailed = false): Payment
    {
        return DB::transaction(function () use ($payment, $params, $forceFailed) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($payment->isCompleted()) {
                return $payment;
            }

            $rc = strtolower((string) ($params['RC'] ?? ''));
            $cancelled = ! $forceFailed && (str_contains($rc, 'cancel') || $rc === '');

            $payment->status = $cancelled ? Payment::STATUS_CANCELLED : Payment::STATUS_FAILED;
            $payment->gateway_response = ['return' => $this->safeReturnParams($params)];
            $payment->save();

            return $payment;
        });
    }

    protected function stringParam(array $params, string $key): ?string
    {
        $value = $params[$key] ?? null;

        return is_string($value) && trim($value) !== '' && strlen($value) <= 100 ? trim($value) : null;
    }

    /**
     * Only the documented, non-secret return fields are ever persisted.
     *
     * @return array<string, string>
     */
    protected function safeReturnParams(array $params): array
    {
        return collect($params)
            ->only(['PRN', 'PID', 'PS', 'RC', 'UID', 'BID', 'BC', 'INI', 'P_AMT', 'R_AMT'])
            ->filter(fn ($value) => is_string($value))
            ->map(fn (string $value) => Str::limit($value, 100, ''))
            ->all();
    }

    protected function formatAmount(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Fonepay's DV: HMAC-SHA512 (upper-case hex) of the given values joined
     * with commas, keyed with the merchant secret.
     *
     * @param  array<int, string>  $values
     */
    protected function sign(array $values): string
    {
        return strtoupper(hash_hmac('sha512', implode(',', $values), (string) $this->secretKey));
    }
}
