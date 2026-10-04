<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\PhoneResetPasswordRequest;
use App\Http\Requests\Auth\SendPhoneResetCodeRequest;
use App\Http\Requests\Auth\VerifyPhoneCodeRequest;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\PhoneNumberService;
use App\Services\PhoneOtpService;
use App\Services\Sms\SmsException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Password recovery by SMS code, alongside (not replacing) the email
 * reset-link flow in AuthController.
 *
 * Forgot password → phone number → code sent → code verified → new password.
 *
 * Only a *verified* phone (users.phone_e164) can recover an account. Every
 * response before the code is verified is identical whether or not the
 * number belongs to an account, so the flow can't be used to discover
 * which numbers are registered. State between steps lives in the session:
 * "phone_reset.phone" after a code is requested, "phone_reset.otp_id" once
 * a code has been verified.
 */
class PhonePasswordResetController extends Controller
{
    private const PURPOSE = PhoneOtp::PURPOSE_PASSWORD_RESET;

    public function __construct(
        protected PhoneOtpService $otps,
        protected PhoneNumberService $phones,
    ) {}

    public function sendCode(SendPhoneResetCodeRequest $request)
    {
        return $this->requestCode($request, $request->e164());
    }

    public function resendCode(Request $request)
    {
        $phone = $request->session()->get('phone_reset.phone');

        if (! $phone) {
            return $this->restart();
        }

        return $this->requestCode($request, $phone);
    }

    public function showVerify(Request $request)
    {
        $phone = $request->session()->get('phone_reset.phone');

        if (! $phone) {
            return $this->restart();
        }

        $sentAt = (int) $request->session()->get('phone_reset.sent_at', 0);

        return view('auth.phone-verify', [
            'maskedPhone' => $this->phones->mask($phone),
            'resendIn' => $this->otps->secondsUntilCanSend($phone, self::PURPOSE),
            'expiresIn' => max(0, $sentAt + config('sms.otp.ttl_minutes') * 60 - now()->timestamp),
        ]);
    }

    public function verifyCode(VerifyPhoneCodeRequest $request)
    {
        $phone = $request->session()->get('phone_reset.phone');

        if (! $phone) {
            return $this->restart();
        }

        $otp = $this->otps->verify($phone, self::PURPOSE, $request->validated('code'));

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => $this->otps->attemptsExhausted($phone, self::PURPOSE)
                    ? 'Too many incorrect attempts. Please request a new code.'
                    : 'That code is incorrect or has expired.',
            ]);
        }

        // New privilege level (allowed to set a password): new session id.
        $request->session()->regenerate();
        $request->session()->forget(['phone_reset.phone', 'phone_reset.sent_at']);
        $request->session()->put('phone_reset.otp_id', $otp->id);

        return redirect()->route('password.phone.reset');
    }

    public function showReset(Request $request)
    {
        if (! $request->session()->has('phone_reset.otp_id')) {
            return $this->restart();
        }

        return view('auth.phone-reset-password');
    }

    public function reset(PhoneResetPasswordRequest $request)
    {
        $otpId = $request->session()->get('phone_reset.otp_id');

        if (! $otpId) {
            return $this->restart();
        }

        $user = DB::transaction(function () use ($otpId, $request) {
            $otp = $this->otps->consumeVerified((int) $otpId, self::PURPOSE);

            // The account's verified phone must still be the one the code
            // went to (it could have been changed in the meantime).
            $user = $otp?->user;

            if (! $user || ! $user->hasVerifiedPhone() || $user->phone_e164 !== $otp->phone) {
                return null;
            }

            $user->forceFill([
                'password' => Hash::make($request->validated('password')),
                'remember_token' => Str::random(60),
            ])->save();

            $this->otps->invalidateForUser($user, self::PURPOSE);

            return $user;
        });

        $request->session()->forget('phone_reset');

        if (! $user) {
            return $this->restart('Your reset session has expired. Please request a new code.');
        }

        // Any outstanding email reset link for this account is now stale too.
        Password::broker()->deleteToken($user);

        event(new PasswordReset($user));

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
    }

    /**
     * Shared by send and resend. Identical outcome for registered and
     * unregistered numbers; the SMS itself is sent after the response.
     */
    protected function requestCode(Request $request, string $phone)
    {
        // Checked before any account lookup: the same answer for everyone.
        if (! $this->otps->smsAvailable()) {
            throw ValidationException::withMessages([
                'phone' => 'Password reset by SMS is not available right now. Please use the Email Address option instead, or contact the hotel.',
            ]);
        }

        if ($wait = $this->otps->secondsUntilCanSend($phone, self::PURPOSE)) {
            $message = "Please wait {$wait} seconds before requesting another code.";

            // From the verify page (resend), show the error there.
            if ($request->routeIs('password.phone.resend')) {
                return redirect()->route('password.phone.verify')->withErrors(['code' => $message]);
            }

            throw ValidationException::withMessages(['phone' => $message]);
        }

        $this->otps->recordSendRequest($phone, self::PURPOSE);

        $user = User::where('phone_e164', $phone)->whereNotNull('phone_verified_at')->first();

        if ($user) {
            try {
                $this->otps->issue($user, $phone, self::PURPOSE, deferDelivery: true);
            } catch (SmsException) {
                // Already logged; the response must stay identical for every number.
            }
        }

        $request->session()->forget('phone_reset.otp_id');
        $request->session()->put('phone_reset.phone', $phone);
        $request->session()->put('phone_reset.sent_at', now()->timestamp);

        return redirect()->route('password.phone.verify')->with(
            'success',
            'If an account uses this verified phone number, we have sent it a 6-digit code.'
        );
    }

    protected function restart(?string $error = null)
    {
        $redirect = redirect()->route('password.request', ['method' => 'phone']);

        return $error ? $redirect->with('error', $error) : $redirect;
    }
}
