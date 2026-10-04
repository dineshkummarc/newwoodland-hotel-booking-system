<?php

namespace App\Services;

use App\Contracts\SmsSenderInterface;
use App\Models\Admin;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\Sms\SmsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

use function Illuminate\Support\defer;

/**
 * Issues and checks 6-digit SMS one-time passcodes.
 *
 * Security properties:
 *  - Codes come from random_int() (CSPRNG) and only an HMAC-SHA256 of the
 *    code, keyed with APP_KEY and bound to purpose/phone/user, is stored —
 *    a leaked table can't be brute-forced offline without the app key.
 *  - Codes expire after sms.otp.ttl_minutes and are single-use.
 *  - Issuing a new code invalidates every earlier code for that phone.
 *  - Send cooldown, hourly send cap and failed-attempt lockout are keyed on
 *    the *phone number*, never on whether an account exists, so their
 *    behaviour (and error messages) is identical for registered and
 *    unregistered numbers — no account enumeration.
 */
class PhoneOtpService
{
    public function __construct(
        protected SmsSenderInterface $sms,
        protected PhoneNumberService $phones,
    ) {}

    /**
     * Seconds until another code may be requested for this phone (0 = now).
     */
    public function secondsUntilCanSend(string $phone, string $purpose): int
    {
        $hourlyKey = $this->limiterKey('send-hourly', $phone, $purpose);

        if (RateLimiter::tooManyAttempts($hourlyKey, config('sms.otp.max_sends_per_hour'))) {
            return max(RateLimiter::availableIn($hourlyKey), 1);
        }

        $cooldownKey = $this->limiterKey('send-cooldown', $phone, $purpose);

        return RateLimiter::tooManyAttempts($cooldownKey, 1)
            ? max(RateLimiter::availableIn($cooldownKey), 1)
            : 0;
    }

    /**
     * Record a send request against the phone's limits. Called for every
     * accepted request whether or not an account matches, and resets the
     * failed-attempt counter so each new code gets a fresh set of tries.
     */
    public function recordSendRequest(string $phone, string $purpose): void
    {
        RateLimiter::hit($this->limiterKey('send-cooldown', $phone, $purpose), config('sms.otp.resend_cooldown_seconds'));
        RateLimiter::hit($this->limiterKey('send-hourly', $phone, $purpose), 3600);
        RateLimiter::clear($this->limiterKey('verify', $phone, $purpose));
    }

    /**
     * Create a new code for $user and SMS it to $phone. Any earlier code for
     * the same phone/purpose stops working immediately.
     *
     * With $deferDelivery the SMS is sent after the HTTP response (so the
     * response time doesn't reveal whether an account exists) and delivery
     * failures are only logged; otherwise they are thrown.
     *
     * @throws SmsException when delivering synchronously and the provider fails
     */
    public function issue(User|Admin $owner, string $phone, string $purpose, bool $deferDelivery = false): PhoneOtp
    {
        if (! $this->sms->isConfigured()) {
            // Never issue a code that cannot be delivered (and never claim one was sent).
            Log::warning('OTP not issued: SMS is not configured.', ['purpose' => $purpose, 'driver' => config('sms.driver')]);

            throw new SmsException('SMS is not configured.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = DB::transaction(function () use ($owner, $phone, $purpose, $code) {
            $this->invalidateOutstanding($phone, $purpose);

            return PhoneOtp::create([
                'user_id' => $owner instanceof User ? $owner->id : null,
                'admin_id' => $owner instanceof Admin ? $owner->id : null,
                'phone' => $phone,
                'purpose' => $purpose,
                'code_hash' => $this->hash($code, $purpose, $phone, $owner instanceof Admin
                    ? $this->ownerKey(null, $owner->id)
                    : $this->ownerKey($owner->id, null)),
                'expires_at' => now()->addMinutes(config('sms.otp.ttl_minutes')),
            ]);
        });

        $deliver = function () use ($otp, $code, $deferDelivery) {
            try {
                $this->sms->send($otp->phone, $this->message($otp->purpose, $code));
            } catch (SmsException $e) {
                // An undeliverable code must not stay usable.
                $otp->forceFill(['consumed_at' => now()])->save();

                Log::warning('OTP SMS delivery failed.', [
                    'otp_id' => $otp->id,
                    'purpose' => $otp->purpose,
                    'to' => $this->phones->mask($otp->phone),
                    'error' => $e->getMessage(),
                ]);

                if (! $deferDelivery) {
                    throw $e;
                }
            }
        };

        $deferDelivery ? defer($deliver) : $deliver();

        return $otp;
    }

    /**
     * Whether this phone has used up its failed attempts for the current code.
     */
    public function attemptsExhausted(string $phone, string $purpose): bool
    {
        return RateLimiter::tooManyAttempts($this->limiterKey('verify', $phone, $purpose), config('sms.otp.max_attempts'));
    }

    /**
     * Check a submitted code against the newest pending code for the phone.
     * Returns the matched (now verified) OTP, or null for a wrong, expired,
     * already-used or non-existent code — callers can't tell these apart,
     * by design. With $consume the code is also marked used immediately;
     * otherwise it stays redeemable once via consumeVerified().
     */
    public function verify(string $phone, string $purpose, string $code, ?int $userId = null, bool $consume = false, ?int $adminId = null): ?PhoneOtp
    {
        if ($this->attemptsExhausted($phone, $purpose)) {
            return null;
        }

        $otp = DB::transaction(function () use ($phone, $purpose, $code, $userId, $adminId, $consume) {
            $otp = PhoneOtp::pending()
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
                ->when($adminId !== null, fn ($query) => $query->where('admin_id', $adminId))
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp) {
                return null;
            }

            if (! hash_equals($otp->code_hash, $this->hash($code, $purpose, $phone, $this->ownerKey($otp->user_id, $otp->admin_id)))) {
                $otp->attempts++;

                if ($otp->attempts >= config('sms.otp.max_attempts')) {
                    $otp->consumed_at = now();
                }

                $otp->save();

                return null;
            }

            $otp->verified_at = now();

            if ($consume) {
                $otp->consumed_at = now();
            }

            $otp->save();

            return $otp;
        });

        $limiterKey = $this->limiterKey('verify', $phone, $purpose);

        if ($otp) {
            RateLimiter::clear($limiterKey);
        } else {
            RateLimiter::hit($limiterKey, config('sms.otp.ttl_minutes') * 60);
        }

        return $otp;
    }

    /**
     * Redeem a verified-but-unused code exactly once (call inside the same
     * transaction as the action it authorizes). Returns null if the code
     * was already used, invalidated, or verified too long ago.
     */
    public function consumeVerified(int $otpId, string $purpose): ?PhoneOtp
    {
        $otp = PhoneOtp::whereKey($otpId)
            ->where('purpose', $purpose)
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->where('verified_at', '>', now()->subMinutes(config('sms.otp.reset_window_minutes')))
            ->lockForUpdate()
            ->first();

        $otp?->forceFill(['consumed_at' => now()])->save();

        return $otp;
    }

    /**
     * Invalidate every not-yet-used code for this user and purpose.
     */
    public function invalidateForUser(User $user, string $purpose): void
    {
        PhoneOtp::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /**
     * Invalidate every not-yet-used code for this staff account and purpose.
     */
    public function invalidateForAdmin(Admin $admin, string $purpose): void
    {
        PhoneOtp::where('admin_id', $admin->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /** Whether codes can be sent at all right now (transport set up). */
    public function smsAvailable(): bool
    {
        return $this->sms->isConfigured();
    }

    /**
     * Whether a sent code really reaches a phone. False for the local "log"
     * driver: pages then say plainly that no text message was sent.
     */
    public function deliversToPhones(): bool
    {
        return $this->sms->deliversToPhones();
    }

    protected function invalidateOutstanding(string $phone, string $purpose): void
    {
        PhoneOtp::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /**
     * What a code is bound to: the customer's id (unchanged format, so codes
     * issued before staff support still verify) or "admin:<id>".
     */
    protected function ownerKey(?int $userId, ?int $adminId): string
    {
        return $adminId !== null ? 'admin:'.$adminId : (string) $userId;
    }

    protected function hash(string $code, string $purpose, string $phone, string $ownerKey): string
    {
        return hash_hmac('sha256', implode('|', [$purpose, $phone, $ownerKey, $code]), config('app.key'));
    }

    protected function message(string $purpose, string $code): string
    {
        $app = config('app.name');
        $minutes = config('sms.otp.ttl_minutes');

        $what = $purpose === PhoneOtp::PURPOSE_PASSWORD_RESET ? 'password reset' : 'phone verification';

        return "{$code} is your {$app} {$what} code. It expires in {$minutes} minutes. Never share this code with anyone.";
    }

    protected function limiterKey(string $type, string $phone, string $purpose): string
    {
        return "phone-otp:{$type}:{$purpose}:".hash('sha256', $phone);
    }
}
